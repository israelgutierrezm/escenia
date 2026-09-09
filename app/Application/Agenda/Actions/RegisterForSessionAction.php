<?php

declare(strict_types=1);

namespace App\Application\Agenda\Actions;

use App\Application\Gamification\AwardPoints;
use App\Domain\Agenda\Exceptions\SessionFullException;
use App\Domain\Agenda\Models\SessionRegistration;
use App\Domain\Events\Models\EventSession;
use App\Domain\Gamification\Enums\PointsAction;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * An attendee adds a session to their agenda. Capacity is enforced (422 when
 * full); the unique index makes a repeat registration idempotent. Registering
 * awards gamification points (once, on the first registration).
 */
final class RegisterForSessionAction
{
    public function __construct(
        private readonly AwardPoints $points,
    ) {}

    public function execute(Attendee $attendee, EventSession $session): SessionRegistration
    {
        if (! $session->hasCapacityLeft()) {
            throw new SessionFullException;
        }

        try {
            $registration = DB::transaction(function () use ($attendee, $session): SessionRegistration {
                $registration = SessionRegistration::query()->create([
                    'event_session_id' => $session->getKey(),
                    'attendee_id' => $attendee->getKey(),
                ]);

                $session->increment('registered_count');

                return $registration;
            });
        } catch (UniqueConstraintViolationException) {
            // Already on their agenda — idempotent, no extra count or points.
            return SessionRegistration::query()
                ->where('event_session_id', $session->getKey())
                ->where('attendee_id', $attendee->getKey())
                ->firstOrFail();
        }

        $this->points->record($attendee, PointsAction::SessionRegistered, $session->ulid);

        return $registration;
    }
}
