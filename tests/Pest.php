<?php

declare(strict_types=1);

use App\Application\Events\Actions\CreateEventAction;
use App\Application\Events\DTOs\CreateEventData;
use App\Application\Identity\Actions\RegisterUserAction;
use App\Application\Identity\DTOs\RegisterUserData;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * Register a brand new user together with their first tenant (owner), returning
 * both. Mirrors the real registration path used across the API.
 *
 * @return array{0: User, 1: Tenant}
 */
function registerTenantOwner(?string $email = null, string $tenantName = 'Acme'): array
{
    $user = app(RegisterUserAction::class)->execute(new RegisterUserData(
        name: 'Test User',
        email: $email ?? fake()->unique()->safeEmail(),
        password: 'password',
        tenantName: $tenantName,
    ));

    /** @var Tenant $tenant */
    $tenant = $user->tenants()->firstOrFail();

    return [$user, $tenant];
}

/**
 * A tenant owner plus a freshly created event in their default workspace.
 *
 * @return array{0: User, 1: Tenant, 2: Event}
 */
function makeEventOwner(string $title = 'Studio Event'): array
{
    // Start each fixture from a clean tenant context. In production every HTTP
    // request resets the scoped TenantContext; the test process reuses one
    // container across sub-requests, so we reset it explicitly here.
    app(TenantContext::class)->forget();

    [$user, $tenant] = registerTenantOwner();
    $workspace = $tenant->workspaces()->firstOrFail();

    $event = app(CreateEventAction::class)->execute($tenant, $workspace, $user, new CreateEventData($title));

    return [$user, $tenant, $event];
}

/**
 * A tenant owner acting via Sanctum, with an event whose registration form is
 * open. Returns the owner, tenant, event and the host auth headers.
 *
 * @return array{0: User, 1: Tenant, 2: Event, 3: array<string, string>}
 */
function makeWebinarHost(string $title = 'Webinar'): array
{
    [$user, $tenant, $event] = makeEventOwner($title);
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    test()->withHeaders($headers)
        ->putJson("/api/v1/events/{$event->ulid}/registration-form", ['is_open' => true, 'fields' => []])
        ->assertOk();

    return [$user, $tenant, $event, $headers];
}

/**
 * Register a public attendee for an event and return their raw join token
 * (the credential for attendee-facing endpoints).
 */
function registerAttendee(string $eventUlid, string $name = 'Ava Attendee', ?string $email = null): string
{
    return test()->postJson("/api/v1/events/{$eventUlid}/register", [
        'name' => $name,
        'email' => $email ?? fake()->unique()->safeEmail(),
    ])->assertCreated()->json('data.token');
}
