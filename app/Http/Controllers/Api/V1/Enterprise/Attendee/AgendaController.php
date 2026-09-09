<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Attendee;

use App\Application\Agenda\Actions\RegisterForSessionAction;
use App\Application\Agenda\Actions\UnregisterFromSessionAction;
use App\Domain\Agenda\Models\SessionRegistration;
use App\Domain\Events\Models\EventSession;
use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\AgendaSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Attendee side of the multi-session agenda: browse, build a personal agenda.
 */
class AgendaController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $attendee = $this->context->attendeeOrFail();

        $sessions = EventSession::query()
            ->where('event_id', $attendee->event_id)
            ->with('track')
            ->orderBy('position')
            ->get();

        return AgendaSessionResource::collection($sessions);
    }

    public function mine(): AnonymousResourceCollection
    {
        $attendee = $this->context->attendeeOrFail();

        $sessionIds = SessionRegistration::query()
            ->where('attendee_id', $attendee->getKey())
            ->pluck('event_session_id');

        $sessions = EventSession::query()->whereIn('id', $sessionIds)->with('track')->orderBy('position')->get();

        return AgendaSessionResource::collection($sessions);
    }

    public function register(RegisterForSessionAction $action, string $session): JsonResponse
    {
        $attendee = $this->context->attendeeOrFail();
        $model = $this->resolveSession($session);

        $action->execute($attendee, $model);

        return AgendaSessionResource::make($model->refresh()->load('track'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function unregister(UnregisterFromSessionAction $action, string $session): AgendaSessionResource
    {
        $attendee = $this->context->attendeeOrFail();
        $model = $this->resolveSession($session);

        $action->execute($attendee, $model);

        return AgendaSessionResource::make($model->refresh()->load('track'));
    }

    private function resolveSession(string $session): EventSession
    {
        return EventSession::query()
            ->where('ulid', $session)
            ->where('event_id', $this->context->attendeeOrFail()->event_id)
            ->firstOrFail();
    }
}
