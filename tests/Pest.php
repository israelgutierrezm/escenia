<?php

declare(strict_types=1);

use App\Application\Identity\Actions\RegisterUserAction;
use App\Application\Identity\DTOs\RegisterUserData;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
