<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Settings;

use App\Application\Settings\UpdateSettingsAction;
use App\Domain\Settings\Contracts\SettingsRepository;
use App\Domain\Settings\DTOs\SettingDefinition;
use App\Domain\Settings\Enums\SettingScope;
use App\Domain\Settings\SettingCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;

/**
 * Platform-wide configuration, editable by a super-admin. Any external-API
 * credential or provider selection in the catalog can be set here instead of via
 * environment variables (ADR-033). Secrets are write-only: their value is never
 * returned, only whether one is set. Route is gated by the `super.admin`
 * middleware.
 */
class SystemSettingsController extends Controller
{
    public function index(SettingsRepository $repository): JsonResponse
    {
        return response()->json(['data' => $this->payload($repository)]);
    }

    public function update(UpdateSettingsRequest $request, UpdateSettingsAction $action, SettingsRepository $repository): JsonResponse
    {
        /** @var list<array{key: string, value: mixed}> $changes */
        $changes = $request->validated('settings');
        $action->execute($request->user(), SettingScope::System, null, $changes);

        return response()->json(['data' => $this->payload($repository)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payload(SettingsRepository $repository): array
    {
        $stored = $repository->storedForScope(SettingScope::System, null);

        return array_map(function (SettingDefinition $definition) use ($stored): array {
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
                $item['value'] = $isSet ? $stored[$definition->key] : $definition->default();
            }

            return $item;
        }, SettingCatalog::forScope(SettingScope::System));
    }
}
