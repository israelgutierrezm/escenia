<?php

declare(strict_types=1);

namespace App\Application\Networking\Actions;

use App\Application\Networking\Events\NetworkingNotification;
use App\Domain\Networking\Enums\MeetingStatus;
use App\Domain\Networking\Exceptions\InvalidMeetingTransitionException;
use App\Domain\Networking\Models\Meeting;
use App\Domain\Registration\Models\Attendee;

/**
 * Either party cancels a meeting (while proposed or accepted). Guarded
 * transition + CAS; records who canceled. The caller resolves the meeting scoped
 * to the acting attendee (proposer or invitee).
 */
final class CancelMeetingAction
{
    public function execute(Meeting $meeting, Attendee $actor): Meeting
    {
        $from = $meeting->status;

        if (! $from->canTransitionTo(MeetingStatus::Canceled)) {
            throw new InvalidMeetingTransitionException($from, MeetingStatus::Canceled);
        }

        $applied = Meeting::query()
            ->whereKey($meeting->getKey())
            ->where('status', $from->value)
            ->update([
                'status' => MeetingStatus::Canceled->value,
                'canceled_by' => $actor->getKey(),
                'responded_at' => now(),
            ]);

        if ($applied === 0) {
            throw new InvalidMeetingTransitionException($from, MeetingStatus::Canceled);
        }

        $meeting->refresh()->loadMissing(['proposer', 'invitee']);

        // Notify the other party (the one who did not cancel).
        $other = $meeting->proposer_id === $actor->getKey() ? $meeting->invitee : $meeting->proposer;
        if ($other !== null) {
            event(new NetworkingNotification(
                $other->ulid,
                'meeting.canceled',
                "{$actor->name} canceló la reunión 1:1.",
            ));
        }

        return $meeting;
    }
}
