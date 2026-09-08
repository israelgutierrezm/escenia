<?php

declare(strict_types=1);

use App\Domain\Events\Models\Event;
use Laravel\Sanctum\Sanctum;

function createEvent(string $tenantUlid, string $workspaceUlid): string
{
    return test()
        ->withHeader('X-Tenant-Id', $tenantUlid)
        ->postJson('/api/v1/events', ['workspace_id' => $workspaceUlid, 'title' => 'Event'])
        ->json('data.id');
}

it('enables a capability the plan entitles', function () {
    [$user, $tenant] = registerTenantOwner(); // free plan includes chat
    $workspace = $tenant->workspaces()->firstOrFail();
    Sanctum::actingAs($user);

    $id = createEvent($tenant->ulid, $workspace->ulid);

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->putJson("/api/v1/events/{$id}/capabilities/chat", ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('data.capability', 'chat')
        ->assertJsonPath('data.enabled', true);
});

it('blocks a capability the plan does not entitle', function () {
    [$user, $tenant] = registerTenantOwner(); // free plan lacks recording
    $workspace = $tenant->workspaces()->firstOrFail();
    Sanctum::actingAs($user);

    $id = createEvent($tenant->ulid, $workspace->ulid);

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->putJson("/api/v1/events/{$id}/capabilities/recording", ['enabled' => true])
        ->assertForbidden()
        ->assertJsonPath('error_code', 'capability_not_entitled');

    $event = Event::withoutGlobalScopes()->where('ulid', $id)->firstOrFail();
    expect($event->capabilities()->count())->toBe(0);
});

it('can always disable a capability regardless of entitlement', function () {
    [$user, $tenant] = registerTenantOwner();
    $workspace = $tenant->workspaces()->firstOrFail();
    Sanctum::actingAs($user);

    $id = createEvent($tenant->ulid, $workspace->ulid);

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->putJson("/api/v1/events/{$id}/capabilities/recording", ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.enabled', false);
});
