<?php

declare(strict_types=1);

use App\Domain\Media\Contracts\MediaEgressProvider;
use App\Domain\Media\Contracts\MediaProviderContract;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laravel\Sanctum\Sanctum;

it('refuses a host token when the studio is not live', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);

    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])
        ->postJson("/api/v1/events/{$event->ulid}/studio/host-token")
        ->assertStatus(409);
});

it('issues a host media token for a live studio', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $this->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/start")->assertOk();

    $response = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/host-token")
        ->assertOk()
        ->assertJsonStructure(['data' => ['access' => ['token', 'url', 'identity', 'room', 'expires_at']]]);

    // Fake provider (the dev/test default) mints a deterministic token.
    expect($response->json('data.access.token'))->toStartWith('fake.');
    expect($response->json('data.access.identity'))->toStartWith('host-');
});

it('mints a valid LiveKit JWT with full host grants', function () {
    config([
        'media.provider' => 'livekit',
        'media.livekit.api_key' => 'devkey',
        'media.livekit.api_secret' => 'secret',
        'media.livekit.url' => 'wss://livekit.test',
    ]);
    app()->forgetInstance(MediaProviderContract::class);
    app()->forgetInstance(MediaEgressProvider::class);

    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $this->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/start")->assertOk();

    $access = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/host-token")
        ->assertOk()
        ->json('data.access');

    expect($access['url'])->toBe('wss://livekit.test');

    $claims = (array) JWT::decode($access['token'], new Key('secret', 'HS256'));
    expect($claims['iss'])->toBe('devkey');
    expect($claims['sub'])->toBe($access['identity']);

    $video = (array) $claims['video'];
    expect($video['room'])->toBe($access['room']);
    expect($video['roomJoin'])->toBeTrue();
    expect($video['canPublish'])->toBeTrue();
    expect($video['canSubscribe'])->toBeTrue();
});
