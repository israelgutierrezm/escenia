<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\TenantMembership;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

it('lists the current tenant workspaces', function () {
    [$user, $tenant] = registerTenantOwner();

    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->getJson('/api/v1/workspaces')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'default');
});

it('creates a workspace as an owner', function () {
    [$user, $tenant] = registerTenantOwner();

    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->postJson('/api/v1/workspaces', ['name' => 'Marketing'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Marketing')
        ->assertJsonPath('data.slug', 'marketing');
});

it('requires a tenant context on tenant-scoped routes', function () {
    [$user] = registerTenantOwner();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/workspaces')->assertStatus(400);
});

it('forbids a member from creating a workspace', function () {
    [, $tenant] = registerTenantOwner();

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

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->postJson('/api/v1/workspaces', ['name' => 'Nope'])
        ->assertForbidden();
});
