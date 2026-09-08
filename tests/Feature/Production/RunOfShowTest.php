<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

it('adds run-of-show items and reorders them', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $first = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/run-of-show", ['title' => 'Opening'])
        ->assertCreated()
        ->json('data.id');

    $second = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/run-of-show", ['title' => 'Main segment'])
        ->json('data.id');

    $reordered = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/run-of-show/reorder", ['items' => [$second, $first]])
        ->assertOk();

    expect(collect($reordered->json('data'))->pluck('id')->all())->toBe([$second, $first]);
});
