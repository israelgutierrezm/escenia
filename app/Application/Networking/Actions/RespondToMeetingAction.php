<?php

declare(strict_types=1);

namespace App\Application\Networking\Actions;

use App\Application\Networking\Events\NetworkingNotification;
use App\Domain\Networking\Enums\MeetingStatus;
use App\Domain\Networking\Exceptions\InvalidMeetingTransitionException;
use App\Domain\Networking\Models\Meeting;

/**
 * The invitee accepts or declines a proposed meeting. Guarded transition + CAS
 * on the source status. The caller resolves the meeting scoped to the invitee.
 */
final class RespondToMeetingAction
{
    public function execute(Meeting $meeting, bool $accept): Meeting
    {
        $from = $meeting->status;
        $target = $accept ? MeetingStatus::Accepted : MeetingStatus::Declined;

        if (! $from->canTransitionTo($target)) {
            throw new InvalidMeetingTransitionException($from, $target);
        }

        $applied = Meeting::query()
            ->whereKey($meeting->getKey())
            ->where('status', $from->value)
            ->update(['status' => $target->value, 'responded_at' => now()]);

        if ($applied === 0) {
            throw new InvalidMeetingTransitionException($from, $target);
        }

        $meeting->refresh()->loadMissing(['proposer', 'invitee']);

        $proposerUlid = $meeting->proposer?->ulid;
        if ($proposerUlid !== null) {
            $actorName = $meeting->invitee->name;
            event(new NetworkingNotification(
                $proposerUlid,
                $accept ? 'meeting.accepted' : 'meeting.declined',
                $accept
                    ? "{$actorName} confirmó tu reunión 1:1."
                    : "{$actorName} rechazó tu reunión 1:1.",
            ));
        }

        return $meeting;
    }
}
