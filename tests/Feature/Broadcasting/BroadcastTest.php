<?php

declare(strict_types=1);

use App\Application\Broadcasting\Actions\StopBroadcastAction;
use App\Domain\Broadcasting\Exceptions\BroadcastTransitionConflictException;
use App\Domain\Broadcasting\Models\BroadcastSession;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantMembership;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * @return array{0: User, 1: Tenant, 2: Event, 3: array<string, string>, 4: list<string>}
 */
function liveStudioWithDestinations(): array
{
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    test()->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/start")->assertOk();

    $first = test()->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/destinations", [
        'name' => 'YouTube', 'protocol' => 'rtmps', 'url' => 'rtmps://a/live', 'stream_key' => 'k1',
    ])->json('data.id');

    $second = test()->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/destinations", [
        'name' => 'Twitch', 'protocol' => 'rtmp', 'url' => 'rtmp://b/live', 'stream_key' => 'k2',
    ])->json('data.id');

    return [$user, $tenant, $event, $headers, [$first, $second]];
}

it('starts a multistream broadcast and stops it', function () {
    [, , $event, $headers, $destinations] = liveStudioWithDestinations();

    $response = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/broadcast/start", ['destinations' => $destinations, 'record' => true]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'live')
        ->assertJsonPath('data.health', 'healthy')
        ->assertJsonPath('data.record', true)
        ->assertJsonCount(2, 'data.destinations');

    $broadcastId = $response->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/broadcasts/{$broadcastId}/stop")
        ->assertOk()
        ->assertJsonPath('data.status', 'ended');
});

it('rejects broadcasting when the studio is not live', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $destination = $this->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/destinations", [
        'name' => 'X', 'protocol' => 'rtmp', 'url' => 'rtmp://a/live', 'stream_key' => 'k',
    ])->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/broadcast/start", ['destinations' => [$destination]])
        ->assertStatus(422);
});

it('rejects stopping an already ended broadcast', function () {
    [, , $event, $headers, $destinations] = liveStudioWithDestinations();

    $broadcastId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/broadcast/start", ['destinations' => $destinations])
        ->json('data.id');

    $this->withHeaders($headers)->postJson("/api/v1/broadcasts/{$broadcastId}/stop")->assertOk();

    $this->withHeaders($headers)
        ->postJson("/api/v1/broadcasts/{$broadcastId}/stop")
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'invalid_broadcast_transition');
});

it('reports broadcast health', function () {
    [, , $event, $headers, $destinations] = liveStudioWithDestinations();

    $broadcastId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/broadcast/start", ['destinations' => $destinations])
        ->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/broadcasts/{$broadcastId}/health", ['health' => 'degraded'])
        ->assertOk()
        ->assertJsonPath('data.health', 'degraded');
});

it('rejects a concurrent stale stop with a conflict', function () {
    [$user, , $event, $headers, $destinations] = liveStudioWithDestinations();

    $broadcastId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/broadcast/start", ['destinations' => $destinations])
        ->json('data.id');

    $broadcast = BroadcastSession::withoutGlobalScopes()->where('ulid', $broadcastId)->firstOrFail();
    $stale = BroadcastSession::withoutGlobalScopes()->where('ulid', $broadcastId)->firstOrFail();

    $action = app(StopBroadcastAction::class);
    $action->execute($broadcast, $user);

    expect(fn () => $action->execute($stale, $user))
        ->toThrow(BroadcastTransitionConflictException::class);
});

it('isolates broadcasts across tenants', function () {
    [, , $eventA, $headersA, $destinationsA] = liveStudioWithDestinations();
    $this->withHeaders($headersA)
        ->postJson("/api/v1/events/{$eventA->ulid}/studio/broadcast/start", ['destinations' => $destinationsA]);

    [$userB, $tenantB] = registerTenantOwner();
    Sanctum::actingAs($userB);

    $this->withHeaders(['X-Tenant-Id' => $tenantB->ulid])
        ->getJson("/api/v1/events/{$eventA->ulid}/studio/broadcast")
        ->assertNotFound();
});

it('forbids a member from starting a broadcast', function () {
    [, $tenant, $event, $headers, $destinations] = liveStudioWithDestinations();

    $member = User::factory()->create();
    TenantMembership::create([
        'tenant_id' => $tenant->id,
        'user_id' => $member->id,
        'role' => 'member',
        'status' => 'active',
        'joined_at' => now(),
    ]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $member->assignRole('member');

    Sanctum::actingAs($member);

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/broadcast/start", ['destinations' => $destinations])
        ->assertForbidden();
});
