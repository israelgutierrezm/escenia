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
use App\Http\Controllers\Api\V1\Tenancy\TenantController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // ---- Public authentication ----
    Route::post('auth/register', RegisterController::class)->middleware('throttle:login');
    Route::post('auth/login', LoginController::class)->middleware('throttle:login');

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
        });
    });
});
