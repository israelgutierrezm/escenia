<?php

declare(strict_types=1);

namespace App\Domain\Settings\Contracts;

use App\Domain\Settings\DTOs\SettingDefinition;
use App\Domain\Settings\Enums\SettingScope;
use App\Domain\Tenancy\Models\Tenant;

/**
 * Persists and resolves configuration values (ADR-033). Resolution precedence is
 * tenant → system → config/env default. Implementations MUST be resilient when
 * the store is unavailable (e.g. before migration) and fall back to the default.
 */
interface SettingsRepository
{
    /**
     * The effective value for a definition, honouring precedence.
     */
    public function resolve(SettingDefinition $definition, ?Tenant $tenant): mixed;

    /**
     * The explicitly-stored values at one scope, keyed by setting key. `scope`
     * is System or Tenant (never Both); `tenant` is required for Tenant scope.
     *
     * @return array<string, mixed>
     */
    public function storedForScope(SettingScope $scope, ?Tenant $tenant): array;

    public function set(SettingScope $scope, ?Tenant $tenant, string $key, mixed $value): void;

    public function forget(SettingScope $scope, ?Tenant $tenant, string $key): void;
}
