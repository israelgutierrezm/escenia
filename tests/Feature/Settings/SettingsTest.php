<?php

declare(strict_types=1);

use App\Domain\Ai\Contracts\AiCompletionProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\Contracts\SettingsRepository;
use App\Domain\Settings\Services\Settings;
use App\Domain\Tenancy\Context\TenantContext;
use App\Infrastructure\Ai\ClaudeCompletionProvider;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function freshSettings(): void
{
    app()->forgetInstance(SettingsRepository::class);
    app()->forgetInstance(Settings::class);
    app()->forgetInstance(AiCompletionProvider::class);
}

// ---- System settings (super-admin) -------------------------------------------

it('lets a super-admin configure a provider from within the system', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/system/settings')
        ->assertOk()
        ->assertJsonFragment(['key' => 'ai.completion', 'value' => 'fake']);

    $this->putJson('/api/v1/system/settings', ['settings' => [
        ['key' => 'ai.completion', 'value' => 'claude'],
        ['key' => 'ai.claude.api_key', 'value' => 'sk-test-123'],
    ]])->assertOk();

    // The stored setting now drives provider selection, with no env change.
    freshSettings();
    expect(app(Settings::class)->get('ai.completion'))->toBe('claude');
    expect(app(AiCompletionProvider::class))->toBeInstanceOf(ClaudeCompletionProvider::class);
});

it('never returns secret values and stores them encrypted', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/system/settings', ['settings' => [
        ['key' => 'ai.claude.api_key', 'value' => 'sk-super-secret'],
    ]])->assertOk();

    $item = collect($this->getJson('/api/v1/system/settings')->json('data'))
        ->firstWhere('key', 'ai.claude.api_key');

    expect($item['is_secret'])->toBeTrue();
    expect($item['is_set'])->toBeTrue();
    expect($item)->not->toHaveKey('value');

    $raw = DB::table('settings')->where('key', 'ai.claude.api_key')->value('value');
    expect($raw)->not->toContain('sk-super-secret');
});

it('requires super-admin for system settings', function () {
    [$owner] = registerTenantOwner();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/system/settings')->assertForbidden();
});

it('rejects an unknown or non-configurable setting', function () {
    $admin = User::factory()->superAdmin()->create();
    Sanctum::actingAs($admin);

    $this->putJson('/api/v1/system/settings', ['settings' => [['key' => 'nope.nope', 'value' => 'x']]])
        ->assertStatus(422);
});

// ---- Tenant settings (owner) + precedence ------------------------------------

it('lets a tenant override a setting, honouring precedence', function () {
    app(TenantContext::class)->forget();
    [$owner, $tenant] = registerTenantOwner();
    Sanctum::actingAs($owner);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $this->withHeaders($headers)->getJson('/api/v1/settings')
        ->assertOk()
        ->assertJsonFragment(['key' => 'ai.completion']);

    $this->withHeaders($headers)->putJson('/api/v1/settings', ['settings' => [
        ['key' => 'ai.completion', 'value' => 'claude'],
    ]])->assertOk();

    freshSettings();
    $settings = app(Settings::class);
    // Tenant override wins for the tenant; system stays at the config default.
    expect($settings->get('ai.completion', null, $tenant))->toBe('claude');
    expect($settings->get('ai.completion'))->toBe('fake');
});

it('rejects a system-only setting at tenant scope', function () {
    app(TenantContext::class)->forget();
    [$owner, $tenant] = registerTenantOwner();
    Sanctum::actingAs($owner);

    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])
        ->putJson('/api/v1/settings', ['settings' => [['key' => 'analytics.capture', 'value' => 'queue']]])
        ->assertStatus(422);
});

it('requires tenant.manage for tenant settings', function () {
    app(TenantContext::class)->forget();
    [$owner, $tenant] = registerTenantOwner();
    $member = User::factory()->create();
    $tenant->memberships()->create([
        'user_id' => $member->getKey(),
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    Sanctum::actingAs($member);

    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])->getJson('/api/v1/settings')->assertForbidden();
});

// ---- Super-admin command -----------------------------------------------------

it('grants and revokes super-admin via the command', function () {
    $user = User::factory()->create();

    $this->artisan('escenia:super-admin', ['email' => $user->email])->assertExitCode(0);
    expect($user->refresh()->is_super_admin)->toBeTrue();

    $this->artisan('escenia:super-admin', ['email' => $user->email, '--revoke' => true])->assertExitCode(0);
    expect($user->refresh()->is_super_admin)->toBeFalse();
});
