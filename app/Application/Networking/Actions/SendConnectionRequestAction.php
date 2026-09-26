<?php

declare(strict_types=1);

namespace App\Application\Networking\Actions;

use App\Application\Networking\Events\NetworkingNotification;
use App\Domain\Networking\Enums\ConnectionStatus;
use App\Domain\Networking\Exceptions\ConnectionAlreadyExistsException;
use App\Domain\Networking\Exceptions\NetworkingUnavailableException;
use App\Domain\Networking\Exceptions\SelfNetworkingException;
use App\Domain\Networking\Models\Connection;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Database\Eloquent\Builder;

/**
 * One attendee requests a connection with another. Rejects self-connection and
 * any existing live connection in either direction; a previously declined
 * request (same direction) is reopened as pending.
 */
final class SendConnectionRequestAction
{
    public function execute(Attendee $requester, Attendee $addressee, ?string $message): Connection
    {
        if ($requester->getKey() === $addressee->getKey()) {
            throw new SelfNetworkingException;
        }

        if (! $addressee->networking_opt_in) {
            throw new NetworkingUnavailableException;
        }

        $live = Connection::query()
            ->where('event_id', $requester->event_id)
            ->whereIn('status', [ConnectionStatus::Pending->value, ConnectionStatus::Accepted->value])
            ->where(function (Builder $q) use ($requester, $addressee): void {
                $q->where(fn (Builder $x) => $x->where('requester_id', $requester->getKey())->where('addressee_id', $addressee->getKey()))
                    ->orWhere(fn (Builder $x) => $x->where('requester_id', $addressee->getKey())->where('addressee_id', $requester->getKey()));
            })
            ->exists();

        if ($live) {
            throw new ConnectionAlreadyExistsException;
        }

        /** @var Connection $connection */
        $connection = Connection::query()->updateOrCreate(
            [
                'event_id' => $requester->event_id,
                'requester_id' => $requester->getKey(),
                'addressee_id' => $addressee->getKey(),
            ],
            [
                'tenant_id' => $requester->tenant_id,
                'status' => ConnectionStatus::Pending->value,
                'message' => $message,
                'responded_at' => null,
            ],
        );

        event(new NetworkingNotification(
            $addressee->ulid,
            'connection.requested',
            "{$requester->name} quiere conectar contigo.",
        ));

        return $connection;
    }
}
