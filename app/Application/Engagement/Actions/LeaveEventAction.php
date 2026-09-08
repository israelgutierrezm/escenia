<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Engagement\Models\AttendeeSession;
use App\Domain\Registration\Models\Attendee;

/**
 * Closes an attendee's current presence session, if one is open. Safe to call
 * when none is open (no-op). A close feeds the analytics plane so the
 * attendance timeline can bound the session.
 */
final class LeaveEventAction
{
    public function __construct(
        private readonly AnalyticsCollector $analytics,
    ) {}

    public function execute(Attendee $attendee): ?AttendeeSession
    {
        $session = AttendeeSession::query()
            ->where('attendee_id', $attendee->getKey())
            ->whereNull('left_at')
            ->latest('id')
            ->first();

        if ($session === null) {
            return null;
        }

        $session->forceFill(['left_at' => now(), 'last_seen_at' => now()])->save();

        $this->analytics->record(
            AnalyticsEventName::AttendanceLeft,
            $attendee->event()->firstOrFail(),
            $attendee,
        );

        return $session;
    }
}
