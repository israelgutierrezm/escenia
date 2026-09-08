<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Automation\Models\Automation;
use App\Domain\Automation\Models\AutomationRun;
use App\Http\Controllers\Controller;
use App\Http\Resources\AutomationRunResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AutomationRunController extends Controller
{
    public function index(Request $request, string $automation): AnonymousResourceCollection
    {
        abort_unless(
            $request->user()?->can(Permission::AutomationsView->value) === true,
            JsonResponse::HTTP_FORBIDDEN,
        );

        $model = Automation::query()->where('ulid', $automation)->firstOrFail();

        $runs = AutomationRun::query()
            ->where('automation_id', $model->getKey())
            ->latest('id')
            ->paginate(50);

        return AutomationRunResource::collection($runs);
    }
}
