<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\TenantMembership;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

it('creates a scene with an initial version at the current schema', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);

    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])
        ->postJson("/api/v1/events/{$event->ulid}/studio/scenes", ['name' => 'Intro'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Intro')
        ->assertJsonPath('data.current_version.version', 1)
        ->assertJsonPath('data.current_version.schema_version', '1.0')
        ->assertJsonPath('data.current_version.is_current', true);
});

it('creates a new version on update and flips the current pointer', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $sceneId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/scenes", ['name' => 'Intro'])
        ->json('data.id');

    $this->withHeaders($headers)
        ->putJson("/api/v1/scenes/{$sceneId}", [
            'definition' => ['layout' => 'split', 'elements' => [['type' => 'text', 'value' => 'Hi']]],
        ])
        ->assertOk()
        ->assertJsonPath('data.current_version.version', 2)
        ->assertJsonPath('data.current_version.schema_version', '1.0')
        ->assertJsonPath('data.current_version.definition.layout', 'split');

    $this->withHeaders($headers)
        ->getJson("/api/v1/scenes/{$sceneId}/versions")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('isolates scenes across tenants', function () {
    [$userA, $tenantA, $eventA] = makeEventOwner();
    Sanctum::actingAs($userA);
    $sceneId = $this->withHeaders(['X-Tenant-Id' => $tenantA->ulid])
        ->postJson("/api/v1/events/{$eventA->ulid}/studio/scenes", ['name' => 'Secret'])
        ->json('data.id');

    [$userB, $tenantB] = registerTenantOwner();
    Sanctum::actingAs($userB);

    $this->withHeaders(['X-Tenant-Id' => $tenantB->ulid])
        ->getJson("/api/v1/scenes/{$sceneId}")
        ->assertNotFound();
});

it('forbids a member from creating a scene', function () {
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
        ->postJson("/api/v1/events/{$event->ulid}/studio/scenes", ['name' => 'Nope'])
        ->assertForbidden();
});
