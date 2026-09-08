<?php

declare(strict_types=1);

use App\Domain\Broadcasting\Models\StreamDestination;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\TenantMembership;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

it('creates a destination with an encrypted, never-exposed stream key', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);

    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])
        ->postJson("/api/v1/events/{$event->ulid}/studio/destinations", [
            'name' => 'YouTube',
            'protocol' => 'rtmps',
            'url' => 'rtmps://a.rtmp.youtube.com/live2',
            'stream_key' => 'super-secret-key',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'YouTube')
        ->assertJsonPath('data.protocol', 'rtmps')
        ->assertJsonMissingPath('data.stream_key');

    $destination = StreamDestination::withoutGlobalScopes()->firstOrFail();

    // Encrypted at rest, but the cast decrypts it transparently.
    expect($destination->getRawOriginal('stream_key'))->not->toBe('super-secret-key')
        ->and($destination->stream_key)->toBe('super-secret-key');
});

it('forbids a member from creating a destination', function () {
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
        ->postJson("/api/v1/events/{$event->ulid}/studio/destinations", [
            'name' => 'X',
            'protocol' => 'rtmp',
            'url' => 'rtmp://a/live',
            'stream_key' => 'k',
        ])
        ->assertForbidden();
});
