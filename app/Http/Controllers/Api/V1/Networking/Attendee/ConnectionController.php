<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Networking\Attendee;

use App\Application\Networking\Actions\RespondToConnectionAction;
use App\Application\Networking\Actions\SendConnectionRequestAction;
use App\Domain\Networking\Models\Connection;
use App\Domain\Registration\Context\AttendeeContext;
use App\Domain\Registration\Models\Attendee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Networking\SendConnectionRequest;
use App\Http\Resources\ConnectionResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Attendee connections: list mine, request a new one, and accept/decline the
 * ones addressed to me. Everything is scoped to my event and my attendee id.
 */
class ConnectionController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $me = $this->context->attendeeOrFail();

        $connections = Connection::query()
            ->where('event_id', $me->event_id)
            ->where(fn (Builder $q) => $q->where('requester_id', $me->getKey())->orWhere('addressee_id', $me->getKey()))
            ->with(['requester', 'addressee'])
            ->latest('id')
            ->get();

        return ConnectionResource::collection($connections);
    }

    public function store(SendConnectionRequest $request, SendConnectionRequestAction $action): JsonResponse
    {
        $me = $this->context->attendeeOrFail();
        $addressee = $this->resolveOther($me, (string) $request->validated('attendee_id'));

        $message = $request->validated('message');
        $connection = $action->execute($me, $addressee, $message !== null ? (string) $message : null);

        return ConnectionResource::make($connection->load(['requester', 'addressee']))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function accept(RespondToConnectionAction $action, string $connection): ConnectionResource
    {
        return $this->respond($action, $connection, true);
    }

    public function decline(RespondToConnectionAction $action, string $connection): ConnectionResource
    {
        return $this->respond($action, $connection, false);
    }

    private function respond(RespondToConnectionAction $action, string $connectionUlid, bool $accept): ConnectionResource
    {
        $me = $this->context->attendeeOrFail();

        $connection = Connection::query()
            ->where('ulid', $connectionUlid)
            ->where('event_id', $me->event_id)
            ->where('addressee_id', $me->getKey())
            ->firstOrFail();

        return ConnectionResource::make($action->execute($connection, $accept)->load(['requester', 'addressee']));
    }

    private function resolveOther(Attendee $me, string $ulid): Attendee
    {
        return Attendee::query()
            ->where('ulid', $ulid)
            ->where('event_id', $me->event_id)
            ->firstOrFail();
    }
}
