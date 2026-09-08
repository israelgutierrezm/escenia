<?php

declare(strict_types=1);

use App\Domain\Production\Events\SceneTaken;
use Illuminate\Support\Facades\Event as EventFacade;
use Laravel\Sanctum\Sanctum;

it('previews then takes a scene to program', function () {
    EventFacade::fake([SceneTaken::class]);

    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);
    $headers = ['X-Tenant-Id' => $tenant->ulid];

    $sceneId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/scenes", ['name' => 'Cam 1'])
        ->json('data.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/preview", ['scene_id' => $sceneId])
        ->assertOk()
        ->assertJsonPath('data.preview_scene', $sceneId);

    // No scene_id -> takes the current preview.
    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/studio/take")
        ->assertOk()
        ->assertJsonPath('data.program_scene', $sceneId);

    EventFacade::assertDispatched(SceneTaken::class);
});

it('rejects a take with no scene and no preview', function () {
    [$user, $tenant, $event] = makeEventOwner();
    Sanctum::actingAs($user);

    $this->withHeaders(['X-Tenant-Id' => $tenant->ulid])
        ->postJson("/api/v1/events/{$event->ulid}/studio/take")
        ->assertStatus(422);
});
