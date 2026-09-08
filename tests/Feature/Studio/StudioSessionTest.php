<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\TenantMembership;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

it('ensures, starts and ends a studio for an event', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $this->withHeaders($headers)->getJson("/api/v1/events/{$event->ulid}/studio")
        ->assertOk()
        ->assertJsonPath('data.status', 'idle')
        ->assertJsonPath('data.current_session', null);

    $this->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'live')
        ->assertJsonPath('data.current_session.status', 'live');

    $this->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/end")
        ->assertOk()
        ->assertJsonPath('data.status', 'idle')
        ->assertJsonPath('data.current_session', null);
});

it('admits a participant and issues a media token', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $this->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/studio/start")->assertOk();

    $response = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/participants", ['name' => 'Ada', 'role' => 'host']);

    $response->assertCreated()
        ->assertJsonPath('data.participant.stage', 'green_room')
        ->assertJsonPath('data.participant.role', 'host')
        ->assertJsonStructure([
            'data' => [
                'participant' => ['id', 'name', 'role', 'stage'],
                'access' => ['token', 'url', 'identity', 'room', 'expires_at'],
            ],
        ]);

    expect($response->json('data.access.token'))->toStartWith('fake.');
});

it('forbids a member from starting a studio', function () {
    [, $tenant, $event] = makeEventOwner();

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

    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])
        ->postJson("/api/v1/events/{$event->ulid}/studio/start")
        ->assertForbidden();
});

it('isolates a studio across tenants', function () {
    [$userA, $tenantA, $eventA] = makeEventOwner();
    Sanctum::actingAs($userA);
    $this->withHeaders(['X-Tenant-Id' => $tenantA->ulid])
        ->postJson("/api/v1/events/{$eventA->ulid}/studio/start")
        ->assertOk();

    [$userB, $tenantB] = registerTenantOwner();
    Sanctum::actingAs($userB);

    // Tenant B cannot even resolve tenant A's event.
    $this->withHeaders(['X-Tenant-Id' => $tenantB->ulid])
        ->getJson("/api/v1/events/{$eventA->ulid}/studio")
        ->assertNotFound();
});
