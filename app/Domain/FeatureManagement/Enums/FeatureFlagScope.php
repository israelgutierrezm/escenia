<?php

declare(strict_types=1);

namespace App\Domain\FeatureManagement\Enums;

/**
 * Resolution scope for a feature flag, ordered from most to least specific.
 * The resolver evaluates user -> workspace -> tenant -> global and returns the
 * first matching decision (see ADR-013).
 */
enum FeatureFlagScope: string
{
    case Global = 'global';
    case Tenant = 'tenant';
    case Workspace = 'workspace';
    case User = 'user';
}
