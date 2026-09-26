<?php

declare(strict_types=1);

namespace App\Application\Networking\Actions;

use App\Application\Networking\Events\NetworkingNotification;
use App\Domain\Networking\Enums\ConnectionStatus;
use App\Domain\Networking\Exceptions\InvalidConnectionTransitionException;
use App\Domain\Networking\Models\Connection;

/**
 * The addressee accepts or declines a pending connection. Guarded transition +
 * compare-and-swap on the source status (a lost race is reported as an invalid
 * transition). The caller resolves the connection scoped to the addressee, so
 * only they can reach this.
 */
final class RespondToConnectionAction
{
    public function execute(Connection $connection, bool $accept): Connection
    {
        $from = $connection->status;
        $target = $accept ? ConnectionStatus::Accepted : ConnectionStatus::Declined;

        if (! $from->canTransitionTo($target)) {
            throw new InvalidConnectionTransitionException($from, $target);
        }

        $applied = Connection::query()
            ->whereKey($connection->getKey())
            ->where('status', $from->value)
            ->update(['status' => $target->value, 'responded_at' => now()]);

        if ($applied === 0) {
            throw new InvalidConnectionTransitionException($from, $target);
        }

        $connection->refresh()->loadMissing(['requester', 'addressee']);

        $requesterUlid = $connection->requester?->ulid;
        if ($requesterUlid !== null) {
            $actorName = $connection->addressee->name;
            event(new NetworkingNotification(
                $requesterUlid,
                $accept ? 'connection.accepted' : 'connection.declined',
                $accept
                    ? "{$actorName} aceptó tu solicitud de conexión."
                    : "{$actorName} rechazó tu solicitud de conexión.",
            ));
        }

        return $connection;
    }
}
