<?php

declare(strict_types=1);

namespace App\Application\Sponsorship\Actions;

use App\Application\Gamification\AwardPoints;
use App\Domain\Gamification\Enums\PointsAction;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Sponsorship\Models\Booth;
use App\Domain\Sponsorship\Models\BoothLead;
use Illuminate\Support\Facades\DB;

/**
 * An attendee visits a booth, becoming a lead for that sponsor. Idempotent per
 * (booth, attendee): the lead count and gamification points only move on the
 * first visit.
 */
final class VisitBoothAction
{
    public function __construct(
        private readonly AwardPoints $points,
    ) {}

    public function execute(Attendee $attendee, Booth $booth, ?string $note): BoothLead
    {
        $lead = DB::transaction(function () use ($attendee, $booth, $note): BoothLead {
            $lead = BoothLead::query()->firstOrCreate(
                ['booth_id' => $booth->getKey(), 'attendee_id' => $attendee->getKey()],
                ['note' => $note],
            );

            if ($lead->wasRecentlyCreated) {
                $booth->increment('leads_count');
            }

            return $lead;
        });

        if ($lead->wasRecentlyCreated) {
            $this->points->record($attendee, PointsAction::BoothVisited, $booth->ulid);
        }

        return $lead;
    }
}
