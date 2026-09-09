<?php

declare(strict_types=1);

namespace App\Application\Education;

use App\Domain\Education\Models\Assessment;
use App\Domain\Education\Models\AssessmentSubmission;
use App\Domain\Education\Models\Certificate;
use App\Domain\Education\Models\CompletionRule;
use App\Domain\Engagement\Models\AttendeeSession;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Support\Str;

/**
 * Evaluates whether an attendee meets an event's completion rule and issues the
 * certificate (ADR-028). Watched time is derived from presence sessions; an open
 * session is bounded by its last heartbeat. Issuance is idempotent per
 * (event, attendee).
 */
final class CertificationService
{
    public function watchedSeconds(Attendee $attendee): int
    {
        $total = 0;

        foreach (AttendeeSession::query()->where('attendee_id', $attendee->getKey())->get() as $session) {
            if ($session->joined_at === null) {
                continue;
            }

            $end = $session->left_at ?? $session->last_seen_at ?? $session->joined_at;
            $total += max(0, $end->getTimestamp() - $session->joined_at->getTimestamp());
        }

        return $total;
    }

    public function isEligible(Event $event, Attendee $attendee, CompletionRule $rule): bool
    {
        if ($rule->min_watch_seconds !== null && $this->watchedSeconds($attendee) < $rule->min_watch_seconds) {
            return false;
        }

        if ($rule->require_assessment) {
            $assessment = Assessment::query()
                ->where('event_id', $event->getKey())
                ->where('is_published', true)
                ->latest('id')
                ->first();

            if ($assessment === null) {
                return false;
            }

            $submission = AssessmentSubmission::query()
                ->where('assessment_id', $assessment->getKey())
                ->where('attendee_id', $attendee->getKey())
                ->first();

            if ($submission === null || ! $submission->passed) {
                return false;
            }
        }

        return true;
    }

    public function issueFor(Event $event, Attendee $attendee): Certificate
    {
        return Certificate::query()->firstOrCreate(
            ['event_id' => $event->getKey(), 'attendee_id' => $attendee->getKey()],
            [
                'code' => $this->generateCode(),
                'recipient_name' => $attendee->name,
                'issued_at' => now(),
            ],
        );
    }

    private function generateCode(): string
    {
        do {
            $code = 'CERT-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (Certificate::query()->withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }
}
