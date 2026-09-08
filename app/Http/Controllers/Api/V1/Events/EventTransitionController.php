<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Events;

use App\Application\Events\Actions\TransitionEventAction;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\TransitionEventRequest;
use App\Http\Resources\EventResource;

class EventTransitionController extends Controller
{
    public function __invoke(TransitionEventRequest $request, TransitionEventAction $action, string $event): EventResource
    {
        $model = Event::query()->where('ulid', $event)->firstOrFail();

        $this->authorize('transition', $model);

        $target = EventStatus::from((string) $request->validated('status'));

        return EventResource::make($action->execute($model, $request->user(), $target));
    }
}
