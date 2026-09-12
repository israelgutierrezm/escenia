<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Application\Agenda\Actions\SetSessionAgendaAction;
use App\Domain\Events\Models\EventSession;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\SetSessionAgendaRequest;
use App\Http\Resources\AgendaSessionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SessionAgendaController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $eventModel = $this->resolveEvent($event);

        $this->authorize('viewEnterprise', $eventModel);

        $sessions = EventSession::query()
            ->where('event_id', $eventModel->getKey())
            ->with('track')
            ->orderBy('position')
            ->get();

        return AgendaSessionResource::collection($sessions);
    }

    public function update(SetSessionAgendaRequest $request, SetSessionAgendaAction $action, string $event, string $session): AgendaSessionResource
    {
        $eventModel = $this->resolveEvent($event);

        $this->authorize('manageEnterprise', $eventModel);

        $sessionModel = EventSession::query()
            ->where('ulid', $session)
            ->where('event_id', $eventModel->getKey())
            ->firstOrFail();

        $track = $request->validated('track');
        $room = $request->validated('room');
        $capacity = $request->validated('capacity');

        $updated = $action->execute(
            $eventModel,
            $sessionModel,
            $track !== null ? (string) $track : null,
            $room !== null ? (string) $room : null,
            $capacity !== null ? (int) $capacity : null,
        );

        return AgendaSessionResource::make($updated->load('track'));
    }
}
