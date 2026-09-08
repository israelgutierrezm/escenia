<?php

declare(strict_types=1);

use App\Application\Events\Actions\CreateEventAction;
use App\Application\Events\Actions\TransitionEventAction;
use App\Application\Events\DTOs\CreateEventData;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Events\EventStatusChanged;
use App\Domain\Events\Exceptions\EventTransitionConflictException;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\Event as EventFacade;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

it('creates a draft event from a template, applying only entitled capabilities', function () {
    [$user, $tenant] = registerTenantOwner();
    $workspace = $tenant->workspaces()->firstOrFail();
    $template = EventTemplate::query()->where('name', 'Webinar')->firstOrFail();

    Sanctum::actingAs($user);

    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->postJson('/api/v1/events', [
            'workspace_id' => $workspace->ulid,
            'title' => 'Launch Webinar',
            'template_id' => $template->ulid,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.type', 'webinar')
        ->assertJsonPath('data.slug', 'launch-webinar');

    // The free plan entitles registration/chat/qa/polls/replay — all 5 defaults.
    $event = Event::withoutGlobalScopes()->where('title', 'Launch Webinar')->firstOrFail();
    expect($event->capabilities()->count())->toBe(5);
});

it('walks the lifecycle through valid transitions and stamps timestamps', function () {
    EventFacade::fake([EventStatusChanged::class]);

    [$user, $tenant] = registerTenantOwner();
    $workspace = $tenant->workspaces()->firstOrFail();
    Sanctum::actingAs($user);

    $id = $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->postJson('/api/v1/events', ['workspace_id' => $workspace->ulid, 'title' => 'Show'])
        ->json('data.id');

    foreach (['scheduled', 'live', 'ended'] as $status) {
        $this->withHeader('X-Tenant-Id', $tenant->ulid)
            ->postJson("/api/v1/events/{$id}/transition", ['status' => $status])
            ->assertOk()
            ->assertJsonPath('data.status', $status);
    }

    $event = Event::withoutGlobalScopes()->where('ulid', $id)->firstOrFail();
    expect($event->actual_start_at)->not->toBeNull()
        ->and($event->actual_end_at)->not->toBeNull();

    EventFacade::assertDispatched(EventStatusChanged::class);
});

it('rejects an illegal transition with 422', function () {
    [$user, $tenant] = registerTenantOwner();
    $workspace = $tenant->workspaces()->firstOrFail();
    Sanctum::actingAs($user);

    $id = $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->postJson('/api/v1/events', ['workspace_id' => $workspace->ulid, 'title' => 'Show'])
        ->json('data.id');

    // draft -> live is not allowed (must schedule first)
    $this->withHeader('X-Tenant-Id', $tenant->ulid)
        ->postJson("/api/v1/events/{$id}/transition", ['status' => 'live'])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'invalid_transition');
});

it('isolates events across tenants', function () {
    [$userA, $tenantA] = registerTenantOwner(tenantName: 'Tenant A');
    $workspaceA = $tenantA->workspaces()->firstOrFail();
    Sanctum::actingAs($userA);
    $idA = $this->withHeader('X-Tenant-Id', $tenantA->ulid)
        ->postJson('/api/v1/events', ['workspace_id' => $workspaceA->ulid, 'title' => 'Secret'])
        ->json('data.id');

    [$userB, $tenantB] = registerTenantOwner(tenantName: 'Tenant B');
    Sanctum::actingAs($userB);

    $this->withHeader('X-Tenant-Id', $tenantB->ulid)
        ->getJson("/api/v1/events/{$idA}")
        ->assertNotFound();
});

it('forbids a member from creating events', function () {
    [, $tenant] = registerTenantOwner();
    $workspace = $tenant->workspaces()->firstOrFail();

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
        ->postJson('/api/v1/events', ['workspace_id' => $workspace->ulid, 'title' => 'Nope'])
        ->assertForbidden();
});

it('rejects a concurrent stale transition via optimistic locking', function () {
    [$user, $tenant] = registerTenantOwner();
    $workspace = $tenant->workspaces()->firstOrFail();

    $event = app(CreateEventAction::class)
        ->execute($tenant, $workspace, $user, new CreateEventData('Race Event'));

    // A second handle captured while the event is still draft (the "loser").
    $stale = Event::withoutGlobalScopes()->findOrFail($event->getKey());

    $action = app(TransitionEventAction::class);

    // First transition wins: draft -> scheduled.
    $action->execute($event, $user, EventStatus::Scheduled);

    // The stale handle still believes it is draft; the compare-and-swap sees the
    // row already moved and conflicts instead of double-transitioning.
    expect(fn () => $action->execute($stale, $user, EventStatus::Scheduled))
        ->toThrow(EventTransitionConflictException::class);

    expect(Event::withoutGlobalScopes()->findOrFail($event->getKey())->status)
        ->toBe(EventStatus::Scheduled);
});
