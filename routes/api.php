<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Events\EventCapabilityController;
use App\Http\Controllers\Api\V1\Events\EventController;
use App\Http\Controllers\Api\V1\Events\EventScheduleController;
use App\Http\Controllers\Api\V1\Events\EventSessionController;
use App\Http\Controllers\Api\V1\Events\EventSpeakerController;
use App\Http\Controllers\Api\V1\Events\EventTemplateController;
use App\Http\Controllers\Api\V1\Events\EventTransitionController;
use App\Http\Controllers\Api\V1\FeatureManagement\FeatureFlagController;
use App\Http\Controllers\Api\V1\Production\BrandKitController;
use App\Http\Controllers\Api\V1\Production\ProductionMixerController;
use App\Http\Controllers\Api\V1\Production\RunOfShowController;
use App\Http\Controllers\Api\V1\Production\SceneController;
use App\Http\Controllers\Api\V1\Studio\GuestJoinController;
use App\Http\Controllers\Api\V1\Studio\StudioController;
use App\Http\Controllers\Api\V1\Studio\StudioGuestLinkController;
use App\Http\Controllers\Api\V1\Studio\StudioParticipantController;
use App\Http\Controllers\Api\V1\Tenancy\TenantController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // ---- Public authentication ----
    Route::post('auth/register', RegisterController::class)->middleware('throttle:login');
    Route::post('auth/login', LoginController::class)->middleware('throttle:login');

    // ---- Public guest join (the guest-link token is the credential) ----
    Route::post('studio/guest/{token}/join', GuestJoinController::class)->middleware('throttle:30,1');

    // ---- Authenticated (any tenant) ----
    Route::middleware(['auth:sanctum', 'share.user'])->group(function (): void {
        Route::post('auth/logout', LogoutController::class);
        Route::get('auth/me', MeController::class);
        Route::get('tenants', [TenantController::class, 'index']);

        // ---- Tenant-scoped ----
        Route::middleware(['tenant', 'tenant.required'])->group(function (): void {
            Route::get('workspaces', [WorkspaceController::class, 'index']);
            Route::post('workspaces', [WorkspaceController::class, 'store']);
            Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show']);
            Route::match(['put', 'patch'], 'workspaces/{workspace}', [WorkspaceController::class, 'update']);
            Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy']);

            Route::get('feature-flags', FeatureFlagController::class);

            // ---- Events (Fase 1 — Event Core) ----
            Route::get('event-templates', [EventTemplateController::class, 'index']);

            Route::get('events', [EventController::class, 'index']);
            Route::post('events', [EventController::class, 'store']);
            Route::get('events/{event}', [EventController::class, 'show']);
            Route::match(['put', 'patch'], 'events/{event}', [EventController::class, 'update']);
            Route::delete('events/{event}', [EventController::class, 'destroy']);

            Route::post('events/{event}/transition', EventTransitionController::class);

            Route::get('events/{event}/capabilities', [EventCapabilityController::class, 'index']);
            Route::put('events/{event}/capabilities/{capability}', [EventCapabilityController::class, 'update']);

            Route::get('events/{event}/sessions', [EventSessionController::class, 'index']);
            Route::post('events/{event}/sessions', [EventSessionController::class, 'store']);

            Route::get('events/{event}/speakers', [EventSpeakerController::class, 'index']);
            Route::post('events/{event}/speakers', [EventSpeakerController::class, 'store']);

            Route::get('events/{event}/schedule', [EventScheduleController::class, 'index']);
            Route::post('events/{event}/schedule', [EventScheduleController::class, 'store']);

            // ---- Studio (Fase 2 — Studio MVP) ----
            Route::get('events/{event}/studio', [StudioController::class, 'show']);
            Route::post('events/{event}/studio/start', [StudioController::class, 'start']);
            Route::post('events/{event}/studio/end', [StudioController::class, 'end']);

            Route::get('events/{event}/studio/participants', [StudioParticipantController::class, 'index']);
            Route::post('events/{event}/studio/participants', [StudioParticipantController::class, 'store']);
            Route::post('studio-participants/{participant}/move', [StudioParticipantController::class, 'move']);
            Route::post('studio-participants/{participant}/token', [StudioParticipantController::class, 'token']);

            Route::get('events/{event}/studio/guest-links', [StudioGuestLinkController::class, 'index']);
            Route::post('events/{event}/studio/guest-links', [StudioGuestLinkController::class, 'store']);
            Route::delete('studio-guest-links/{link}', [StudioGuestLinkController::class, 'destroy']);

            // ---- Production Engine (Fase 3) ----
            Route::get('events/{event}/studio/scenes', [SceneController::class, 'index']);
            Route::post('events/{event}/studio/scenes', [SceneController::class, 'store']);
            Route::get('scenes/{scene}', [SceneController::class, 'show']);
            Route::match(['put', 'patch'], 'scenes/{scene}', [SceneController::class, 'update']);
            Route::get('scenes/{scene}/versions', [SceneController::class, 'versions']);

            Route::post('events/{event}/studio/preview', [ProductionMixerController::class, 'preview']);
            Route::post('events/{event}/studio/take', [ProductionMixerController::class, 'take']);

            Route::get('events/{event}/studio/brand-kits', [BrandKitController::class, 'index']);
            Route::post('events/{event}/studio/brand-kits', [BrandKitController::class, 'store']);
            Route::post('brand-kits/{kit}/default', [BrandKitController::class, 'setDefault']);

            Route::get('events/{event}/studio/run-of-show', [RunOfShowController::class, 'index']);
            Route::post('events/{event}/studio/run-of-show', [RunOfShowController::class, 'store']);
            Route::post('events/{event}/studio/run-of-show/reorder', [RunOfShowController::class, 'reorder']);
        });
    });
});
