<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantMembership;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

/**
 * A tenant owner acting via Sanctum plus the host tenant headers.
 *
 * @return array{0: User, 1: Tenant, 2: array<string, string>}
 */
function rbacOwner(): array
{
    app(TenantContext::class)->forget();
    [$user, $tenant] = registerTenantOwner();
    Sanctum::actingAs($user);

    return [$user, $tenant, ['X-Tenant-Id' => $tenant->ulid]];
}

/**
 * Add a member to a tenant with the given base role (membership + Spatie role).
 */
function addMember(Tenant $tenant, TenantRole $role): User
{
    $user = User::factory()->create();

    TenantMembership::create([
        'tenant_id' => $tenant->getKey(),
        'user_id' => $user->getKey(),
        'role' => $role,
        'status' => MembershipStatus::Active,
        'joined_at' => now(),
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $user->assignRole($role->value);

    return $user;
}

// ---- Custom roles ------------------------------------------------------------

it('creates a custom role and lists it alongside the system roles', function () {
    [, , $headers] = rbacOwner();

    $this->withHeaders($headers)
        ->postJson('/api/v1/roles', ['name' => 'Analyst', 'permissions' => ['analytics.view', 'events.view']])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Analyst')
        ->assertJsonPath('data.is_system', false);

    $names = collect($this->withHeaders($headers)->getJson('/api/v1/roles')->assertOk()->json('data'))->pluck('name');

    expect($names)->toContain('Analyst')->toContain('owner')->toContain('admin')->toContain('member');
});

it('updates and deletes a custom role', function () {
    [, , $headers] = rbacOwner();

    $this->withHeaders($headers)->postJson('/api/v1/roles', ['name' => 'Editor', 'permissions' => ['content.view']])->assertCreated();

    $updated = $this->withHeaders($headers)
        ->putJson('/api/v1/roles/Editor', ['permissions' => ['content.view', 'content.manage']])
        ->assertOk()
        ->json('data.permissions');
    expect($updated)->toContain('content.view')->toContain('content.manage');

    $this->withHeaders($headers)->deleteJson('/api/v1/roles/Editor')->assertNoContent();
    $this->withHeaders($headers)->putJson('/api/v1/roles/Editor', ['permissions' => ['content.view']])->assertNotFound();
});

it('rejects creating a role that shadows a system role', function () {
    [, , $headers] = rbacOwner();

    $this->withHeaders($headers)
        ->postJson('/api/v1/roles', ['name' => 'owner', 'permissions' => ['events.view']])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'system_role');
});

// ---- Per-user roles & permissions --------------------------------------------

it('assigns a custom role to a member and grants its permissions', function () {
    [, $tenant, $headers] = rbacOwner();
    $this->withHeaders($headers)->postJson('/api/v1/roles', ['name' => 'Publisher', 'permissions' => ['content.manage']])->assertCreated();

    $member = addMember($tenant, TenantRole::Member);

    $this->withHeaders($headers)
        ->putJson("/api/v1/members/{$member->ulid}/roles", ['roles' => ['Publisher']])
        ->assertOk()
        ->assertJsonPath('data.custom_roles', ['Publisher']);

    $access = $this->withHeaders($headers)->getJson("/api/v1/members/{$member->ulid}")->assertOk()->json('data');
    expect($access['effective_permissions'])->toContain('content.manage');
});

it('grants a direct permission to a member', function () {
    [, $tenant, $headers] = rbacOwner();
    $member = addMember($tenant, TenantRole::Member);

    $this->withHeaders($headers)
        ->putJson("/api/v1/members/{$member->ulid}/permissions", ['permissions' => ['commerce.manage']])
        ->assertOk()
        ->assertJsonPath('data.direct_permissions', ['commerce.manage']);

    $access = $this->withHeaders($headers)->getJson("/api/v1/members/{$member->ulid}")->json('data');
    expect($access['effective_permissions'])->toContain('commerce.manage');
});

it('changes a member base tier and mirrors it to the role assignment', function () {
    [, $tenant, $headers] = rbacOwner();
    $member = addMember($tenant, TenantRole::Member);

    $this->withHeaders($headers)
        ->putJson("/api/v1/members/{$member->ulid}/membership-role", ['role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('data.membership_role', 'admin');

    $this->assertDatabaseHas('tenant_memberships', [
        'tenant_id' => $tenant->getKey(), 'user_id' => $member->getKey(), 'role' => 'admin',
    ]);

    $access = $this->withHeaders($headers)->getJson("/api/v1/members/{$member->ulid}")->json('data');
    expect($access['effective_permissions'])->toContain('members.manage');
});

// ---- Security guards ----------------------------------------------------------

it('forbids granting permissions the actor does not hold', function () {
    [, $tenant] = rbacOwner();
    $admin = addMember($tenant, TenantRole::Admin);
    Sanctum::actingAs($admin);

    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])
        ->postJson('/api/v1/roles', ['name' => 'Sneaky', 'permissions' => ['tenant.manage']])
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'privilege_escalation');
});

it('prevents demoting the last owner', function () {
    [$owner, $tenant, $headers] = rbacOwner();

    $this->withHeaders($headers)
        ->putJson("/api/v1/members/{$owner->ulid}/membership-role", ['role' => 'admin'])
        ->assertStatus(403)
        ->assertJsonPath('error_code', 'privilege_escalation');
});

it('requires members.manage to manage roles', function () {
    [, $tenant] = rbacOwner();
    $member = addMember($tenant, TenantRole::Member);
    Sanctum::actingAs($member);

    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])->getJson('/api/v1/roles')->assertForbidden();
});

it('exposes the grouped permission catalog', function () {
    [, , $headers] = rbacOwner();

    $data = $this->withHeaders($headers)->getJson('/api/v1/permissions')->assertOk()->json('data');
    expect($data)->not->toBeEmpty();
    expect(collect($data)->pluck('group'))->toContain('events')->toContain('members');
});
