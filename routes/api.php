<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
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
        });
    });
});
