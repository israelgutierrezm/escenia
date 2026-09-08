<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

it('adds sessions, speakers and schedule items and returns them on show', function () {
    [$user, $tenant] = registerTenantOwner();
    $workspace = $tenant->workspaces()->firstOrFail();
    Sanctum::actingAs($user);

    $id = $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->postJson('/api/v1/events', ['workspace_id' => $workspace->ulid, 'title' => 'Conference'])
        ->json('data.id');

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->postJson("/api/v1/events/{$id}/sessions", ['title' => 'Keynote'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Keynote')
        ->assertJsonPath('data.status', 'scheduled');

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->postJson("/api/v1/events/{$id}/speakers", ['name' => 'Grace Hopper', 'role' => 'host'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Grace Hopper')
        ->assertJsonPath('data.role', 'host');

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->postJson("/api/v1/events/{$id}/schedule", ['title' => 'Opening remarks'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Opening remarks');

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->getJson("/api/v1/events/{$id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.sessions')
        ->assertJsonCount(1, 'data.speakers')
        ->assertJsonCount(1, 'data.schedule');
});

it('lists the templates visible to the tenant', function () {
    [$user, $tenant] = registerTenantOwner();
    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->getJson('/api/v1/event-templates')
        ->assertOk()
        ->assertJsonPath('data.0.is_system', true);
});
