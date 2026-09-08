<?php

declare(strict_types=1);

use App\Application\Workspaces\Actions\CreateWorkspaceAction;
use App\Application\Workspaces\DTOs\CreateWorkspaceData;
use Laravel\Sanctum\Sanctum;

it('forbids establishing context for a tenant the user is not a member of', function () {
    [, $tenantA] = registerTenantOwner(tenantName: 'Tenant A');
    [$userB] = registerTenantOwner(tenantName: 'Tenant B');

    Sanctum::actingAs($userB);

    $this->withHeader('X-Tenant-Id', $tenantA->ulid)
        ->getJson('/api/v1/workspaces')
        ->assertForbidden();
});

it('does not resolve a workspace that belongs to another tenant', function () {
    [$userA, $tenantA] = registerTenantOwner(tenantName: 'Tenant A');
    $workspaceA = app(CreateWorkspaceAction::class)
        ->execute($tenantA, $userA, new CreateWorkspaceData('Secret Room'));

    [$userB, $tenantB] = registerTenantOwner(tenantName: 'Tenant B');

    Sanctum::actingAs($userB);

    // Same public id, but under tenant B's context -> 404 (isolated).
    $this->withHeader('X-Tenant-Id', $tenantB->ulid)
        ->getJson("/api/v1/workspaces/{$workspaceA->ulid}")
        ->assertNotFound();
});

it('allows a member to resolve their own tenant workspace', function () {
    [$userA, $tenantA] = registerTenantOwner(tenantName: 'Tenant A');
    $workspaceA = app(CreateWorkspaceAction::class)
        ->execute($tenantA, $userA, new CreateWorkspaceData('War Room'));

    Sanctum::actingAs($userA);

    $this->withHeader('X-Tenant-Id', $tenantA->ulid)
        ->getJson("/api/v1/workspaces/{$workspaceA->ulid}")
        ->assertOk()
        ->assertJsonPath('data.id', $workspaceA->ulid);
});

it('lists only workspaces of the resolved tenant', function () {
    [$userA, $tenantA] = registerTenantOwner(tenantName: 'Tenant A');
    app(CreateWorkspaceAction::class)->execute($tenantA, $userA, new CreateWorkspaceData('A-Extra'));

    [$userB, $tenantB] = registerTenantOwner(tenantName: 'Tenant B');

    Sanctum::actingAs($userB);

    $response = $this->withHeader('X-Tenant-Id', $tenantB->ulid)
        ->getJson('/api/v1/workspaces')
        ->assertOk();

    // Tenant B only sees its own default workspace, never Tenant A's.
    $slugs = collect($response->json('data'))->pluck('slug');
    expect($slugs)->toContain('default')
        ->and($slugs)->not->toContain('a-extra');
});
