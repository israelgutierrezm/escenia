<?php

declare(strict_types=1);

namespace App\Application\Networking\Actions;

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

        return $meeting->refresh();
    }
}
