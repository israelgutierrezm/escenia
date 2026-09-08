<?php

declare(strict_types=1);

namespace App\Application\Analytics;

use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\Events\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Reads the analytics plane (`analytics_events`) and derives the host-facing
 * reports: attendance, watch time, engagement, the concurrency heatmap and
 * attribution. This is the single query surface over analytics; when the store
 * moves to ClickHouse (ADR-005) only this service changes, not its callers.
 *
 * At MVP scale it reconstructs presence sessions in PHP from the join/heartbeat/
 * leave stream. High-volume events will push this aggregation down to the
 * warehouse (see technical-debt).
 *
 * @phpstan-type Session array{attendee_id: int, start: CarbonImmutable, end: CarbonImmutable}
 */
final class EventAnalyticsService
{
    private const ENGAGEMENT_COUNTS = [
        'chat_messages' => AnalyticsEventName::EngagementChat,
        'questions_asked' => AnalyticsEventName::EngagementQuestionAsked,
        'question_votes' => AnalyticsEventName::EngagementQuestionVoted,
        'poll_votes' => AnalyticsEventName::EngagementPollVoted,
        'resource_downloads' => AnalyticsEventName::EngagementResourceDownloaded,
    ];

    /**
     * @return array<string, mixed>
     */
    public function summary(Event $event): array
    {
        $registrations = $this->countOf($event, AnalyticsEventName::RegistrationCompleted);
        $registeredAttendees = $this->distinctAttendees($event, AnalyticsEventName::RegistrationCompleted);
        $attendedAttendees = $this->distinctAttendees($event, AnalyticsEventName::AttendanceJoined);

        $sessions = $this->reconstructSessions($event);
        $watchSeconds = $sessions->sum(fn (array $s): int => (int) $s['end']->diffInSeconds($s['start']));

        return [
            'registrations' => $registrations,
            'registered_attendees' => $registeredAttendees,
            'attended_attendees' => $attendedAttendees,
            'attendance_rate' => $registeredAttendees > 0
                ? round($attendedAttendees / $registeredAttendees, 4)
                : 0.0,
            'peak_concurrent' => $this->peakConcurrency($sessions),
            'avg_watch_minutes' => $attendedAttendees > 0
                ? round(($watchSeconds / $attendedAttendees) / 60, 1)
                : 0.0,
            'engagement' => $this->engagementTotals($event),
        ];
    }

    /**
     * Concurrency (people present) per time bucket — the attendance heatmap.
     *
     * @return array<string, mixed>
     */
    public function attendanceTimeline(Event $event, int $buckets = 60): array
    {
        $sessions = $this->reconstructSessions($event);

        if ($sessions->isEmpty()) {
            return ['interval_seconds' => 0, 'points' => []];
        }

        $start = $sessions->min(fn (array $s): CarbonImmutable => $s['start']);
        $end = $sessions->max(fn (array $s): CarbonImmutable => $s['end']);
        $span = max(1, $end->diffInSeconds($start));
        $interval = (int) max(1, ceil($span / max(1, $buckets)));

        $points = [];
        for ($t = 0; $t < $span; $t += $interval) {
            $bucketStart = $start->addSeconds($t);
            $bucketEnd = $start->addSeconds($t + $interval);

            $concurrent = $sessions->filter(
                fn (array $s): bool => $s['start'] < $bucketEnd && $s['end'] >= $bucketStart
            )->count();

            $points[] = ['t' => $bucketStart->toIso8601String(), 'concurrent' => $concurrent];
        }

        return ['interval_seconds' => $interval, 'points' => $points];
    }

    /**
     * @return array<string, mixed>
     */
    public function engagement(Event $event): array
    {
        return [
            'totals' => $this->engagementTotals($event),
        ];
    }

    /**
     * Registrations and resulting attendance grouped by acquisition source.
     *
     * @return array<string, mixed>
     */
    public function attribution(Event $event): array
    {
        $attended = $this->attendedAttendeeIds($event);

        $rows = AnalyticsEvent::query()
            ->where('event_id', $event->getKey())
            ->where('name', AnalyticsEventName::RegistrationCompleted->value)
            ->get(['attendee_id', 'properties']);

        /** @var array<string, array{source: string, registrations: int, attended: int}> $bySource */
        $bySource = [];

        foreach ($rows as $row) {
            $properties = $row->properties ?? [];
            $attribution = is_array($properties['attribution'] ?? null) ? $properties['attribution'] : [];
            $source = isset($attribution['utm_source']) && $attribution['utm_source'] !== ''
                ? (string) $attribution['utm_source']
                : 'direct';

            $bySource[$source] ??= ['source' => $source, 'registrations' => 0, 'attended' => 0];
            $bySource[$source]['registrations']++;

            if ($row->attendee_id !== null && $attended->has((int) $row->attendee_id)) {
                $bySource[$source]['attended']++;
            }
        }

        $sources = array_values($bySource);
        usort($sources, static fn (array $a, array $b): int => $b['registrations'] <=> $a['registrations']);

        return ['sources' => $sources];
    }

    /**
     * @return array<string, int>
     */
    private function engagementTotals(Event $event): array
    {
        $totals = [];

        foreach (self::ENGAGEMENT_COUNTS as $key => $name) {
            $totals[$key] = $this->countOf($event, $name);
        }

        return $totals;
    }

    private function countOf(Event $event, AnalyticsEventName $name): int
    {
        return AnalyticsEvent::query()
            ->where('event_id', $event->getKey())
            ->where('name', $name->value)
            ->count();
    }

    private function distinctAttendees(Event $event, AnalyticsEventName $name): int
    {
        return AnalyticsEvent::query()
            ->where('event_id', $event->getKey())
            ->where('name', $name->value)
            ->whereNotNull('attendee_id')
            ->distinct()
            ->count('attendee_id');
    }

    /**
     * A set of attendee ids that attended, keyed by id for O(1) membership.
     *
     * @return Collection<int, int>
     */
    private function attendedAttendeeIds(Event $event): Collection
    {
        return AnalyticsEvent::query()
            ->where('event_id', $event->getKey())
            ->where('name', AnalyticsEventName::AttendanceJoined->value)
            ->whereNotNull('attendee_id')
            ->pluck('attendee_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->flip();
    }

    /**
     * Rebuild presence sessions from the join/heartbeat/leave stream. Per
     * attendee: a `joined` opens a session, `heartbeat`/`left` extend its end,
     * and a `left` (or the next `joined`) closes it. An unclosed session ends at
     * its last seen event.
     *
     * @return Collection<int, Session>
     */
    private function reconstructSessions(Event $event): Collection
    {
        $events = AnalyticsEvent::query()
            ->where('event_id', $event->getKey())
            ->whereIn('name', [
                AnalyticsEventName::AttendanceJoined->value,
                AnalyticsEventName::AttendanceHeartbeat->value,
                AnalyticsEventName::AttendanceLeft->value,
            ])
            ->whereNotNull('attendee_id')
            ->orderBy('attendee_id')
            ->orderBy('occurred_at')
            ->get(['attendee_id', 'name', 'occurred_at']);

        /** @var Collection<int, Session> $sessions */
        $sessions = new Collection;

        /** @var array{attendee_id: int, start: CarbonImmutable, end: CarbonImmutable}|null $open */
        $open = null;

        foreach ($events as $row) {
            $attendeeId = (int) $row->attendee_id;
            $at = CarbonImmutable::instance($row->occurred_at);

            if ($row->name === AnalyticsEventName::AttendanceJoined) {
                if ($open !== null) {
                    $sessions->push($open);
                }
                $open = ['attendee_id' => $attendeeId, 'start' => $at, 'end' => $at];

                continue;
            }

            if ($open === null || $open['attendee_id'] !== $attendeeId) {
                // Heartbeat/leave without a matching join (e.g. joined before
                // analytics existed): treat it as a zero-length touch.
                if ($open !== null) {
                    $sessions->push($open);
                }
                $open = ['attendee_id' => $attendeeId, 'start' => $at, 'end' => $at];
            } else {
                $open['end'] = $at;
            }

            if ($row->name === AnalyticsEventName::AttendanceLeft) {
                $sessions->push($open);
                $open = null;
            }
        }

        if ($open !== null) {
            $sessions->push($open);
        }

        return $sessions;
    }

    /**
     * True peak concurrency via a sweep line over session boundaries.
     *
     * @param  Collection<int, Session>  $sessions
     */
    private function peakConcurrency(Collection $sessions): int
    {
        /** @var list<array{0: int, 1: int}> $deltas */
        $deltas = [];

        foreach ($sessions as $session) {
            $deltas[] = [$session['start']->getTimestamp(), 1];
            $deltas[] = [$session['end']->getTimestamp(), -1];
        }

        // Open (+1) before close (-1) at the same instant, so a point-in-time
        // session (join with no later heartbeat/leave) still counts as present.
        usort($deltas, static fn (array $a, array $b): int => $a[0] <=> $b[0] ?: $b[1] <=> $a[1]);

        $current = 0;
        $peak = 0;

        foreach ($deltas as [$_, $delta]) {
            $current += $delta;
            $peak = max($peak, $current);
        }

        return $peak;
    }
}
