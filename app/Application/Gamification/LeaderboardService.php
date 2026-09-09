<?php

declare(strict_types=1);

namespace App\Application\Gamification;

use App\Domain\Events\Models\Event;
use App\Domain\Gamification\Models\PointsAward;
use App\Domain\Registration\Models\Attendee;

/**
 * Aggregates the gamification ledger into a leaderboard and per-attendee totals.
 */
final class LeaderboardService
{
    /**
     * @return list<array{attendee: string, name: string, points: int}>
     */
    public function forEvent(Event $event, int $limit = 20): array
    {
        $rows = PointsAward::query()
            ->where('event_id', $event->getKey())
            ->groupBy('attendee_id')
            ->selectRaw('attendee_id, SUM(points) as total')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get();

        $attendees = Attendee::query()
            ->whereIn('id', $rows->pluck('attendee_id'))
            ->get()
            ->keyBy('id');

        return $rows->map(function (object $row) use ($attendees): array {
            $attendee = $attendees->get($row->attendee_id);

            return [
                'attendee' => $attendee !== null ? $attendee->ulid : '',
                'name' => $attendee !== null ? $attendee->name : '',
                'points' => (int) $row->total,
            ];
        })->all();
    }

    public function pointsFor(Attendee $attendee): int
    {
        return (int) PointsAward::query()->where('attendee_id', $attendee->getKey())->sum('points');
    }
}
