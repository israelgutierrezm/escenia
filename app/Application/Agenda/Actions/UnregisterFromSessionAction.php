<?php

declare(strict_types=1);

namespace App\Application\Agenda\Actions;

use App\Domain\Agenda\Models\SessionRegistration;
use App\Domain\Events\Models\EventSession;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Support\Facades\DB;

/**
 * An attendee removes a session from their agenda. Earned points are kept.
 */
final class UnregisterFromSessionAction
{
    public function execute(Attendee $attendee, EventSession $session): void
    {
        DB::transaction(function () use ($attendee, $session): void {
            $deleted = SessionRegistration::query()
                ->where('event_session_id', $session->getKey())
                ->where('attendee_id', $attendee->getKey())
                ->delete();

            if ($deleted > 0 && $session->registered_count > 0) {
                $session->decrement('registered_count');
            }
        });
    }
}
