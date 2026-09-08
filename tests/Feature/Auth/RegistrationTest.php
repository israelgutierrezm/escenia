<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantMembership;
use App\Domain\Workspaces\Models\Workspace;

it('registers a user with a tenant, owner membership and default workspace', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'tenant_name' => 'Analytical Engines',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'tenants']]);

    // The public id is a ULID; the internal auto-increment key is never exposed.
    expect($response->json('data.id'))->toBeString()->toHaveLength(26);

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    expect(array_key_exists('id', $user->toArray()))->toBeFalse();

    expect(Tenant::count())->toBe(1);
    expect(TenantMembership::where('user_id', $user->id)->where('role', 'owner')->exists())->toBeTrue();
    expect(
        Workspace::withoutGlobalScopes()->where('slug', 'default')->exists()
    )->toBeTrue();
});

it('validates registration input', function () {
    $this->postJson('/api/v1/auth/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'tenant_name']);
});

it('rejects a duplicate email', function () {
    registerTenantOwner('dup@example.com');

    $this->postJson('/api/v1/auth/register', [
        'name' => 'X',
        'email' => 'dup@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'tenant_name' => 'Y',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);
});
