<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Engagement\Models\AttendeeSession;
use App\Domain\Registration\Models\Attendee;

/**
 * Opens (or refreshes) an attendee's presence session. If an open session
 * already exists it is treated as a heartbeat — `last_seen_at` is bumped;
 * otherwise a new session is opened. Idempotent enough to be called on every
 * heartbeat from the client. Each call also feeds the analytics plane so the
 * attendance timeline can be reconstructed.
 */
final class JoinEventAction
{
    public function __construct(
        private readonly AnalyticsCollector $analytics,
    ) {}

    public function execute(Attendee $attendee): AttendeeSession
    {
        $session = AttendeeSession::query()
            ->where('attendee_id', $attendee->getKey())
            ->whereNull('left_at')
            ->latest('id')
            ->first();

        if ($session !== null) {
            $session->forceFill(['last_seen_at' => now()])->save();

            $this->analytics->record(
                AnalyticsEventName::AttendanceHeartbeat,
                $attendee->event()->firstOrFail(),
                $attendee,
            );

            return $session;
        }

        $session = AttendeeSession::query()->create([
            'attendee_id' => $attendee->getKey(),
            'joined_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->analytics->record(
            AnalyticsEventName::AttendanceJoined,
            $attendee->event()->firstOrFail(),
            $attendee,
        );

        return $session;
    }
}
