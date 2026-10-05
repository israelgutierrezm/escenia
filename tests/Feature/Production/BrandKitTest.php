<?php

declare(strict_types=1);

use App\Domain\Production\Models\BrandKit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Creates a default brand kit for the event and returns its public id.
 *
 * @param  array<string, string>  $headers
 */
function makeBrandKit(string $eventUlid, array $headers): string
{
    return test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/studio/brand-kits", ['name' => 'Marca', 'is_default' => true])
        ->assertCreated()->json('data.id');
}

it('creates a default brand kit and unsets the previous default', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/brand-kits", ['name' => 'Kit One', 'is_default' => true])
        ->assertCreated();

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/brand-kits", ['name' => 'Kit Two', 'is_default' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);

    $list = $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/studio/brand-kits")
        ->assertOk();

    $defaults = collect($list->json('data'))->where('is_default', true)->pluck('name');
    expect($defaults)->toHaveCount(1)->and($defaults->first())->toBe('Kit Two');
});

it('promotes an existing kit to default', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $kitId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/brand-kits", ['name' => 'Kit One'])
        ->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/brand-kits/{$kitId}/default")
        ->assertOk()
        ->assertJsonPath('data.is_default', true);
});

it('uploads a logo to a brand kit and exposes it as a data URI', function () {
    Storage::fake('local');
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];
    $kitId = makeBrandKit($event->ulid, $headers);

    $res = $this->withHeaders($headers)
        ->post("/api/v1/brand-kits/{$kitId}/logo", ['logo' => UploadedFile::fake()->image('logo.png', 120, 120)])
        ->assertOk()
        ->assertJsonPath('data.has_logo', true);

    expect($res->json('data.logo'))->toStartWith('data:image/');

    $kit = BrandKit::withoutGlobalScopes()->where('ulid', $kitId)->firstOrFail();
    Storage::disk('local')->assertExists($kit->logo_path);
    expect($kit->logoDataUri())->toStartWith('data:image/');
});

it('removes a brand kit logo', function () {
    Storage::fake('local');
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];
    $kitId = makeBrandKit($event->ulid, $headers);

    $this->withHeaders($headers)
        ->post("/api/v1/brand-kits/{$kitId}/logo", ['logo' => UploadedFile::fake()->image('logo.png')])
        ->assertOk();

    $this->withHeaders($headers)
        ->deleteJson("/api/v1/brand-kits/{$kitId}/logo")
        ->assertOk()
        ->assertJsonPath('data.has_logo', false)
        ->assertJsonPath('data.logo', null);
});

it('rejects a non-image logo upload', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];
    $kitId = makeBrandKit($event->ulid, $headers);

    $this->withHeaders($headers)
        ->post("/api/v1/brand-kits/{$kitId}/logo", ['logo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
        ->assertStatus(422);
});
