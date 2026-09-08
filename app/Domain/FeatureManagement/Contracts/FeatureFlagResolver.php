<?php

declare(strict_types=1);

namespace App\Domain\FeatureManagement\Contracts;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Workspaces\Models\Workspace;

/**
 * Resolves feature flags with precedence user -> workspace -> tenant -> global,
 * with optional percentage rollout (see ADR-013).
 */
interface FeatureFlagResolver
{
    public function enabled(
        string $key,
        ?User $user = null,
        ?Workspace $workspace = null,
        ?Tenant $tenant = null,
    ): bool;

    public function value(
        string $key,
        mixed $default = null,
        ?User $user = null,
        ?Workspace $workspace = null,
        ?Tenant $tenant = null,
    ): mixed;
}
