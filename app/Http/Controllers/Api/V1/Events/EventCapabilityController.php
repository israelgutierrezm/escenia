<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Events;

use App\Application\Events\Actions\SetCapabilityAction;
use App\Domain\Events\Enums\Capability;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\SetCapabilityRequest;
use App\Http\Resources\EventCapabilityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EventCapabilityController extends Controller
{
    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('view', $model);

        return EventCapabilityResource::collection($model->capabilities()->get());
    }

    public function update(SetCapabilityRequest $request, SetCapabilityAction $action, string $event, string $capability): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageCapabilities', $model);

        $resolved = Capability::tryFrom($capability);
        abort_if($resolved === null, Response::HTTP_NOT_FOUND, 'Unknown capability.');

        /** @var array<string, mixed>|null $settings */
        $settings = $request->validated('settings');

        $record = $action->execute(
            $model,
            $request->user(),
            $resolved,
            (bool) $request->validated('enabled'),
            $settings,
        );

        // PUT is idempotent (set-state), so always respond 200, never 201.
        return EventCapabilityResource::make($record)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }
}
