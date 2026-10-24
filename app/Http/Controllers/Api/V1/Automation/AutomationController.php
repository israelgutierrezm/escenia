<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Application\Automation\Actions\CreateAutomationAction;
use App\Application\Automation\Actions\RotateWebhookSecretAction;
use App\Application\Automation\Actions\SetAutomationActiveAction;
use App\Application\Automation\DTOs\CreateAutomationData;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Automation\Models\Automation;
use App\Domain\Automation\Models\AutomationStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\Automation\CreateAutomationRequest;
use App\Http\Requests\Automation\SetAutomationActiveRequest;
use App\Http\Resources\AutomationResource;
use App\Http\Resources\IssuedWebhookSecretResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tenant-level management of automations. Automations are not event-scoped;
 * their conditions can filter by event attributes. Webhook signing secrets are
 * returned only when issued (creation, rotation), never cached (ADR-038).
 */
class AutomationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::AutomationsView);

        return AutomationResource::collection(
            Automation::query()->with('steps')->latest('id')->get()
        );
    }

    public function store(CreateAutomationRequest $request, CreateAutomationAction $action): JsonResponse
    {
        $this->authorizePermission($request, Permission::AutomationsManage);

        $created = $action->execute($request->user(), CreateAutomationData::fromArray($request->validated()));

        return AutomationResource::make($created->automation)
            ->additional(['webhook_secrets' => IssuedWebhookSecretResource::collection($created->webhookSecrets)->resolve($request)])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED)
            ->header('Cache-Control', 'no-store');
    }

    public function setActive(SetAutomationActiveRequest $request, SetAutomationActiveAction $action, string $automation): AutomationResource
    {
        $this->authorizePermission($request, Permission::AutomationsManage);

        $model = Automation::query()->where('ulid', $automation)->firstOrFail();

        return AutomationResource::make(
            $action->execute($request->user(), $model, (bool) $request->validated('is_active'))->load('steps')
        );
    }

    public function rotateWebhookSecret(Request $request, RotateWebhookSecretAction $action, string $automation, string $step): JsonResponse
    {
        $this->authorizePermission($request, Permission::AutomationsManage);

        $model = Automation::query()->where('ulid', $automation)->firstOrFail();
        $stepModel = AutomationStep::query()
            ->where('automation_id', $model->getKey())
            ->where('ulid', $step)
            ->firstOrFail();

        return IssuedWebhookSecretResource::make($action->execute($request->user(), $stepModel))
            ->response()
            ->header('Cache-Control', 'no-store');
    }

    private function authorizePermission(Request $request, Permission $permission): void
    {
        abort_unless(
            $request->user()?->can($permission->value) === true,
            JsonResponse::HTTP_FORBIDDEN,
        );
    }
}
