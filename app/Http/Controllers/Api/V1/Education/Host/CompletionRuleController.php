<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Education\Host;

use App\Application\Education\Actions\SaveCompletionRuleAction;
use App\Domain\Education\Models\CompletionRule;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Education\SaveCompletionRuleRequest;
use Illuminate\Http\JsonResponse;

class CompletionRuleController extends Controller
{
    use ResolvesEvent;

    public function show(string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEducation', $model);

        $rule = CompletionRule::query()->where('event_id', $model->getKey())->first();

        return response()->json([
            'data' => $rule !== null ? [
                'min_watch_seconds' => $rule->min_watch_seconds,
                'require_assessment' => $rule->require_assessment,
            ] : null,
        ]);
    }

    public function save(SaveCompletionRuleRequest $request, SaveCompletionRuleAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageEducation', $model);

        $minWatch = $request->validated('min_watch_seconds');

        $rule = $action->execute(
            $model,
            $request->user(),
            $minWatch !== null ? (int) $minWatch : null,
            (bool) $request->validated('require_assessment'),
        );

        return response()->json([
            'data' => [
                'min_watch_seconds' => $rule->min_watch_seconds,
                'require_assessment' => $rule->require_assessment,
            ],
        ]);
    }
}
