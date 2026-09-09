<?php

declare(strict_types=1);

namespace App\Infrastructure\Settings;

use App\Domain\Settings\Contracts\SettingsRepository;
use App\Domain\Settings\DTOs\SettingDefinition;
use App\Domain\Settings\Enums\SettingScope;
use App\Domain\Settings\Models\Setting;
use App\Domain\Tenancy\Models\Tenant;
use Throwable;

/**
 * MySQL-backed settings store. Resolution honours precedence (tenant → system →
 * config default) and is memoized per request. Reads are defensive: if the store
 * is unavailable (e.g. before migration, or config:cache during deploy) the
 * config/env default is used, so booting never depends on the settings table.
 */
final class DatabaseSettingsRepository implements SettingsRepository
{
    /**
     * @var array<string, mixed>
     */
    private array $memo = [];

    public function resolve(SettingDefinition $definition, ?Tenant $tenant): mixed
    {
        $cacheKey = ($tenant?->getKey() ?? 'system').'|'.$definition->key;

        if (array_key_exists($cacheKey, $this->memo)) {
            return $this->memo[$cacheKey];
        }

        return $this->memo[$cacheKey] = $this->resolveUncached($definition, $tenant);
    }

    private function resolveUncached(SettingDefinition $definition, ?Tenant $tenant): mixed
    {
        try {
            if ($definition->scope->allowsTenant() && $tenant !== null) {
                $row = Setting::query()
                    ->where('scope', SettingScope::Tenant->value)
                    ->where('tenant_id', $tenant->getKey())
                    ->where('key', $definition->key)
                    ->first();

                if ($row !== null) {
                    return $this->decode($row->value);
                }
            }

            if ($definition->scope->allowsSystem()) {
                $row = Setting::query()
                    ->where('scope', SettingScope::System->value)
                    ->whereNull('tenant_id')
                    ->where('key', $definition->key)
                    ->first();

                if ($row !== null) {
                    return $this->decode($row->value);
                }
            }
        } catch (Throwable) {
            // Store unavailable — fall back to the config/env default below.
        }

        return $definition->default();
    }

    public function storedForScope(SettingScope $scope, ?Tenant $tenant): array
    {
        $query = Setting::query()->where('scope', $scope->value);

        if ($scope === SettingScope::Tenant) {
            $query->where('tenant_id', $tenant?->getKey());
        } else {
            $query->whereNull('tenant_id');
        }

        $stored = [];

        foreach ($query->get() as $row) {
            $stored[$row->key] = $this->decode($row->value);
        }

        return $stored;
    }

    public function set(SettingScope $scope, ?Tenant $tenant, string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            [
                'scope' => $scope->value,
                'tenant_id' => $scope === SettingScope::Tenant ? $tenant?->getKey() : null,
                'key' => $key,
            ],
            ['value' => $this->encode($value)],
        );

        $this->memo = [];
    }

    public function forget(SettingScope $scope, ?Tenant $tenant, string $key): void
    {
        Setting::query()
            ->where('scope', $scope->value)
            ->where('tenant_id', $scope === SettingScope::Tenant ? $tenant?->getKey() : null)
            ->where('key', $key)
            ->delete();

        $this->memo = [];
    }

    private function encode(mixed $value): string
    {
        return (string) json_encode($value);
    }

    private function decode(string $value): mixed
    {
        return json_decode($value, true);
    }
}
