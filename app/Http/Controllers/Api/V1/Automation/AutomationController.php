<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Application\Automation\Actions\CreateAutomationAction;
use App\Application\Automation\Actions\SetAutomationActiveAction;
use App\Application\Automation\DTOs\CreateAutomationData;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Automation\Models\Automation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Automation\CreateAutomationRequest;
use App\Http\Requests\Automation\SetAutomationActiveRequest;
use App\Http\Resources\AutomationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tenant-level management of automations. Automations are not event-scoped;
 * their conditions can filter by event attributes.
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

        $automation = $action->execute($request->user(), CreateAutomationData::fromArray($request->validated()));

        return AutomationResource::make($automation)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function setActive(SetAutomationActiveRequest $request, SetAutomationActiveAction $action, string $automation): AutomationResource
    {
        $this->authorizePermission($request, Permission::AutomationsManage);

        $model = Automation::query()->where('ulid', $automation)->firstOrFail();

        return AutomationResource::make(
            $action->execute($request->user(), $model, (bool) $request->validated('is_active'))->load('steps')
        );
    }

    private function authorizePermission(Request $request, Permission $permission): void
    {
        abort_unless(
            $request->user()?->can($permission->value) === true,
            JsonResponse::HTTP_FORBIDDEN,
        );
    }
}
