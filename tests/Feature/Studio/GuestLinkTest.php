<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

/**
 * @return array{0: string, 1: array<string, string>} [eventUlid, auth headers]
 */
function liveStudio(): array
{
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];
    test()->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/start")->assertOk();

    return [$event->ulid, $headers];
}

it('creates a guest link and a guest redeems it into the live session', function () {
    [$eventUlid, $headers] = liveStudio();

    $create = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/studio/guest-links", ['name' => 'VIP', 'role' => 'guest']);

    $create->assertCreated()->assertJsonStructure(['data' => ['link' => ['id', 'name', 'role'], 'token', 'join_url']]);

    $token = $create->json('data.token');

    // Public redeem: no auth, no tenant header.
    $this->postJson("/api/v1/studio/guest/{$token}/join", ['name' => 'Visitor'])
        ->assertCreated()
        ->assertJsonPath('data.participant.role', 'guest')
        ->assertJsonPath('data.participant.name', 'Visitor')
        ->assertJsonStructure([
            'data' => [
                'participant' => ['id', 'stage'],
                'session' => ['id', 'status'],
                'access' => ['token', 'url', 'room'],
            ],
        ]);
});

it('rejects a revoked guest link', function () {
    [$eventUlid, $headers] = liveStudio();

    $create = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/studio/guest-links", ['name' => 'VIP', 'role' => 'guest']);
    $token = $create->json('data.token');
    $linkId = $create->json('data.link.id');

    $this->withHeaders($headers)->deleteJson("/api/v1/studio-guest-links/{$linkId}")->assertNoContent();

    $this->postJson("/api/v1/studio/guest/{$token}/join", ['name' => 'Visitor'])
        ->assertForbidden()
        ->assertJsonPath('error_code', 'guest_link_invalid');
});

it('enforces single-use guest links', function () {
    [$eventUlid, $headers] = liveStudio();

    $token = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/studio/guest-links", ['name' => 'One', 'role' => 'guest', 'single_use' => true])
        ->json('data.token');

    $this->postJson("/api/v1/studio/guest/{$token}/join", ['name' => 'First'])->assertCreated();
    $this->postJson("/api/v1/studio/guest/{$token}/join", ['name' => 'Second'])->assertForbidden();
});

it('rejects an unknown guest token', function () {
    $this->postJson('/api/v1/studio/guest/not-a-real-token/join', ['name' => 'X'])
        ->assertForbidden()
        ->assertJsonPath('error_code', 'guest_link_invalid');
});
