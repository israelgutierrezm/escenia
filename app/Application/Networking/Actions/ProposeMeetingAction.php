<?php

declare(strict_types=1);

namespace App\Application\Networking\Actions;

use App\Application\Networking\DTOs\ProposeMeetingData;
use App\Domain\Networking\Enums\MeetingStatus;
use App\Domain\Networking\Exceptions\SelfNetworkingException;
use App\Domain\Networking\Models\Meeting;
use App\Domain\Registration\Models\Attendee;

/**
 * An attendee proposes a 1:1 meeting to another. Rejects self-proposals; the
 * meeting opens as `proposed` for the invitee to accept or decline.
 */
final class ProposeMeetingAction
{
    public function execute(Attendee $proposer, Attendee $invitee, ProposeMeetingData $data): Meeting
    {
        if ($proposer->getKey() === $invitee->getKey()) {
            throw new SelfNetworkingException;
        }

        /** @var Meeting $meeting */
        $meeting = Meeting::query()->create([
            'tenant_id' => $proposer->tenant_id,
            'event_id' => $proposer->event_id,
            'proposer_id' => $proposer->getKey(),
            'invitee_id' => $invitee->getKey(),
            'status' => MeetingStatus::Proposed->value,
            'scheduled_at' => $data->scheduledAt,
            'duration_minutes' => $data->durationMinutes,
            'topic' => $data->topic,
        ]);

        return $meeting;
    }
}
