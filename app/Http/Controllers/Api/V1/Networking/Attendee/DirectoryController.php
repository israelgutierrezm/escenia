<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Networking\Attendee;

use App\Domain\Networking\Enums\ConnectionStatus;
use App\Domain\Networking\Models\Connection;
use App\Domain\Registration\Context\AttendeeContext;
use App\Domain\Registration\Models\Attendee;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * People directory for networking: other attendees of the event and this
 * attendee's connection status with each. Names are visible to fellow attendees
 * (the nature of an event directory); an opt-in gate is future work (TD-033).
 */
class DirectoryController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $me = $this->context->attendeeOrFail();
        $query = trim((string) $request->query('q', ''));

        $others = Attendee::query()
            ->where('event_id', $me->event_id)
            ->where('networking_opt_in', true)
            ->whereKeyNot($me->getKey())
            ->when($query !== '', fn (Builder $q): Builder => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit(50)
            ->get();

        // This attendee's connections for the event, keyed by the other person.
        $byOther = [];
        $mine = Connection::query()
            ->where('event_id', $me->event_id)
            ->where(fn (Builder $q) => $q->where('requester_id', $me->getKey())->orWhere('addressee_id', $me->getKey()))
            ->get();
        foreach ($mine as $connection) {
            $otherId = $connection->requester_id === $me->getKey() ? $connection->addressee_id : $connection->requester_id;
            $byOther[$otherId] = $connection;
        }

        $data = $others->map(function (Attendee $person) use ($byOther, $me): array {
            $connection = $byOther[$person->getKey()] ?? null;
            $status = 'none';
            if ($connection !== null) {
                $status = match (true) {
                    $connection->status === ConnectionStatus::Accepted => 'connected',
                    $connection->status === ConnectionStatus::Pending && $connection->requester_id === $me->getKey() => 'pending_out',
                    $connection->status === ConnectionStatus::Pending => 'pending_in',
                    default => 'none',
                };
            }

            return [
                'id' => $person->ulid,
                'name' => $person->name,
                'connection_status' => $status,
                'connection_id' => $connection?->ulid,
            ];
        })->all();

        return response()->json(['data' => $data]);
    }
}
