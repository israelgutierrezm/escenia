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

/**
 * A real upload (not a fake, whose type follows its name): the type is sniffed
 * from the bytes, so the client-supplied name can lie — as it can in production.
 */
function makeRealUpload(string $bytes, string $clientName): UploadedFile
{
    $path = (string) tempnam(sys_get_temp_dir(), 'upl');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $clientName, null, null, true);
}

function makePngBytes(int $width = 32, int $height = 32): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
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

it('names the stored logo from its sniffed type, not the client file name', function () {
    Storage::fake('local');
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];
    $kitId = makeBrandKit($event->ulid, $headers);

    $this->withHeaders($headers)
        ->post("/api/v1/brand-kits/{$kitId}/logo", ['logo' => makeRealUpload(makePngBytes(), 'logo.html')])
        ->assertOk();

    $kit = BrandKit::withoutGlobalScopes()->where('ulid', $kitId)->firstOrFail();
    expect($kit->logo_path)->toBe("brand-kits/{$kitId}/logo.png")
        ->and($kit->logo_mime)->toBe('image/png');
});

it('rejects SVG markup even when it is named as a PNG', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];
    $kitId = makeBrandKit($event->ulid, $headers);

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><image href="file:///etc/passwd"/></svg>';

    $this->withHeaders($headers)
        ->post("/api/v1/brand-kits/{$kitId}/logo", ['logo' => makeRealUpload($svg, 'logo.png')])
        ->assertStatus(422);
});

it('rejects a logo over the pixel cap', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];
    $kitId = makeBrandKit($event->ulid, $headers);

    // Default cap is 2000px a side; the file itself is tiny.
    $this->withHeaders($headers)
        ->post("/api/v1/brand-kits/{$kitId}/logo", ['logo' => UploadedFile::fake()->image('wide.png', 2100, 10)])
        ->assertStatus(422);
});
