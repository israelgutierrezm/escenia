<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

it('creates a default brand kit and unsets the previous default', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/brand-kits", ['name' => 'Kit One', 'is_default' => true])
        ->assertCreated();

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/brand-kits", ['name' => 'Kit Two', 'is_default' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);

    $list = $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/studio/brand-kits")
        ->assertOk();

    $defaults = collect($list->json('data'))->where('is_default', true)->pluck('name');
    expect($defaults)->toHaveCount(1)->and($defaults->first())->toBe('Kit Two');
});

it('promotes an existing kit to default', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $kitId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/brand-kits", ['name' => 'Kit One'])
        ->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/brand-kits/{$kitId}/default")
        ->assertOk()
        ->assertJsonPath('data.is_default', true);
});
