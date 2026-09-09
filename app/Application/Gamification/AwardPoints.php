<?php

declare(strict_types=1);

namespace App\Application\Gamification;

use App\Domain\Gamification\Enums\PointsAction;
use App\Domain\Gamification\Models\PointsAward;
use App\Domain\Registration\Models\Attendee;

/**
 * Awards points to an attendee for an action, idempotently: the unique
 * (attendee, action, subject) index means the same action on the same subject is
 * counted once. Called from the actions that earn points (agenda / expo).
 */
final class AwardPoints
{
    public function record(Attendee $attendee, PointsAction $action, string $subject): void
    {
        PointsAward::query()->firstOrCreate(
            [
                'attendee_id' => $attendee->getKey(),
                'action' => $action->value,
                'subject' => $subject,
            ],
            [
                'event_id' => $attendee->event_id,
                'points' => $action->points(),
                'created_at' => now(),
            ],
        );
    }
}
