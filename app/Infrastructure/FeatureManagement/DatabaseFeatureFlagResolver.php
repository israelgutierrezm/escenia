<?php

declare(strict_types=1);

namespace App\Infrastructure\FeatureManagement;

use App\Domain\FeatureManagement\Contracts\FeatureFlagResolver;
use App\Domain\FeatureManagement\Enums\FeatureFlagScope;
use App\Domain\FeatureManagement\Models\FeatureFlag;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Contracts\Database\Query\Builder;

final class DatabaseFeatureFlagResolver implements FeatureFlagResolver
{
    public function enabled(
        string $key,
        ?User $user = null,
        ?Workspace $workspace = null,
        ?Tenant $tenant = null,
    ): bool {
        $flag = $this->resolve($key, $user, $workspace, $tenant);

        if ($flag === null || ! $flag->enabled) {
            return false;
        }

        if ($flag->rollout_percentage === null) {
            return true;
        }

        return $this->inRollout($key, $user, $flag->rollout_percentage);
    }

    public function value(
        string $key,
        mixed $default = null,
        ?User $user = null,
        ?Workspace $workspace = null,
        ?Tenant $tenant = null,
    ): mixed {
        $flag = $this->resolve($key, $user, $workspace, $tenant);

        if ($flag === null) {
            return $default;
        }

        return $flag->value ?? $default;
    }

    private function resolve(
        string $key,
        ?User $user,
        ?Workspace $workspace,
        ?Tenant $tenant,
    ): ?FeatureFlag {
        $candidates = FeatureFlag::query()
            ->where('key', $key)
            ->where(function (Builder $query) use ($user, $workspace, $tenant): void {
                $query->where('scope', FeatureFlagScope::Global->value);

                if ($tenant !== null) {
                    $query->orWhere(fn (Builder $q) => $q
                        ->where('scope', FeatureFlagScope::Tenant->value)
                        ->where('tenant_id', $tenant->getKey()));
                }

                if ($workspace !== null) {
                    $query->orWhere(fn (Builder $q) => $q
                        ->where('scope', FeatureFlagScope::Workspace->value)
                        ->where('workspace_id', $workspace->getKey()));
                }

                if ($user !== null) {
                    $query->orWhere(fn (Builder $q) => $q
                        ->where('scope', FeatureFlagScope::User->value)
                        ->where('user_id', $user->getKey()));
                }
            })
            ->get();

        $priority = [
            FeatureFlagScope::User->value => 4,
            FeatureFlagScope::Workspace->value => 3,
            FeatureFlagScope::Tenant->value => 2,
            FeatureFlagScope::Global->value => 1,
        ];

        return $candidates
            ->sortByDesc(fn (FeatureFlag $flag): int => $priority[$flag->scope->value])
            ->first();
    }

    private function inRollout(string $key, ?User $user, int $percentage): bool
    {
        if ($percentage <= 0) {
            return false;
        }

        if ($percentage >= 100) {
            return true;
        }

        $seed = $key.':'.($user?->getKey() ?? 'anonymous');

        return (crc32($seed) % 100) < $percentage;
    }
}
