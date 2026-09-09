<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Application\Settings\UpdateSettingsAction;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Settings\Contracts\SettingsRepository;
use App\Domain\Settings\DTOs\SettingDefinition;
use App\Domain\Settings\Enums\SettingScope;
use App\Domain\Settings\Services\Settings;
use App\Domain\Settings\SettingCatalog;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Models\Tenant;
use App\Http\Concerns\AuthorizesTenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-tenant configuration, editable by the tenant Owner (`tenant.manage`). A
 * tenant may override the settings that are tenant-scoped (e.g. bring its own
 * integration key); where it does not, the inherited system/default value is
 * shown as the effective value. Secrets are write-only.
 */
class TenantSettingsController extends Controller
{
    use AuthorizesTenantPermission;

    public function index(Request $request, SettingsRepository $repository, Settings $settings, TenantContext $tenantContext): JsonResponse
    {
        $this->authorizePermission($request, Permission::TenantManage);

        return response()->json(['data' => $this->payload($repository, $settings, $tenantContext->tenantOrFail())]);
    }

    public function update(UpdateSettingsRequest $request, UpdateSettingsAction $action, SettingsRepository $repository, Settings $settings, TenantContext $tenantContext): JsonResponse
    {
        $this->authorizePermission($request, Permission::TenantManage);

        $tenant = $tenantContext->tenantOrFail();
        /** @var list<array{key: string, value: mixed}> $changes */
        $changes = $request->validated('settings');
        $action->execute($request->user(), SettingScope::Tenant, $tenant, $changes);

        return response()->json(['data' => $this->payload($repository, $settings, $tenant)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payload(SettingsRepository $repository, Settings $settings, Tenant $tenant): array
    {
        $stored = $repository->storedForScope(SettingScope::Tenant, $tenant);

        return array_map(function (SettingDefinition $definition) use ($stored, $settings): array {
            $isSet = array_key_exists($definition->key, $stored);

            $item = [
                'key' => $definition->key,
                'group' => $definition->group,
                'type' => $definition->type->value,
                'label' => $definition->label,
                'help' => $definition->help,
                'options' => $definition->options,
                'is_secret' => $definition->isSecret(),
                'is_set' => $isSet,
            ];

            if (! $definition->isSecret()) {
                // Effective: the tenant override if present, else the inherited
                // system/default value (resolved with no tenant override).
                $item['value'] = $isSet ? $stored[$definition->key] : $settings->get($definition->key);
            }

            return $item;
        }, SettingCatalog::forScope(SettingScope::Tenant));
    }
}
