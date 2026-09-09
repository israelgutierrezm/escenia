<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\Contracts\SettingsRepository;
use App\Domain\Settings\DTOs\SettingDefinition;
use App\Domain\Settings\Enums\SettingScope;
use App\Domain\Settings\Enums\SettingType;
use App\Domain\Settings\SettingCatalog;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * Applies a batch of setting changes at a scope. Each key is validated against
 * the catalog (unknown or wrongly-scoped keys are rejected) and coerced to its
 * declared type. Secrets are write-only: an empty value leaves the stored secret
 * untouched. Secret values are NEVER written to the audit trail.
 */
final class UpdateSettingsAction
{
    public function __construct(
        private readonly SettingsRepository $repository,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{key: string, value: mixed}>  $changes
     */
    public function execute(User $actor, SettingScope $scope, ?Tenant $tenant, array $changes): void
    {
        $applied = [];

        foreach ($changes as $change) {
            $key = $change['key'];
            $definition = SettingCatalog::find($key);

            $allowed = $definition !== null && ($scope === SettingScope::System
                ? $definition->scope->allowsSystem()
                : $definition->scope->allowsTenant());

            if (! $allowed) {
                throw ValidationException::withMessages(['settings' => "Unknown or non-configurable setting: {$key}."]);
            }

            // Secrets are write-only: blank means "keep the current value".
            if ($definition->type === SettingType::Secret && (string) $change['value'] === '') {
                continue;
            }

            $this->repository->set($scope, $tenant, $key, $this->coerce($definition, $change['value']));
            $applied[] = $key;
        }

        $this->audit->log('settings.updated', actor: $actor, tenant: $tenant, context: [
            'scope' => $scope->value,
            'keys' => $applied,
        ]);
    }

    private function coerce(SettingDefinition $definition, mixed $value): mixed
    {
        return match ($definition->type) {
            SettingType::Bool => (bool) $value,
            SettingType::Int => (int) $value,
            SettingType::Select => in_array((string) $value, $definition->options, true)
                ? (string) $value
                : throw ValidationException::withMessages(['settings' => "Setting {$definition->key} has an invalid option."]),
            SettingType::Json => is_array($value)
                ? $value
                : throw ValidationException::withMessages(['settings' => "Setting {$definition->key} must be an object."]),
            default => (string) $value, // String, Secret
        };
    }
}
