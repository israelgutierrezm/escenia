<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ai;

use App\Application\Ai\Actions\DesignEventAction;
use App\Domain\Events\Models\Event;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\DesignEventRequest;
use Illuminate\Http\JsonResponse;

/**
 * Event Architect: turn a brief into a proposed event plan.
 */
class EventArchitectController extends Controller
{
    use ResolvesEvent;

    public function design(DesignEventRequest $request, DesignEventAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('create', Event::class);

        $result = $action->execute($model, (string) $request->validated('brief'));

        return response()->json(['data' => $result]);
    }
}
