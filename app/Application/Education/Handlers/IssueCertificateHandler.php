<?php

declare(strict_types=1);

namespace App\Application\Education\Handlers;

use App\Application\Education\CertificationService;
use App\Domain\Education\Models\CompletionRule;
use App\Domain\Events\Models\Event;
use App\Domain\Outbox\Contracts\OutboxHandler;
use App\Domain\Outbox\Models\OutboxEvent;
use App\Domain\Registration\Models\Attendee;

/**
 * Outbox consumer (ADR-007 / ADR-028): after an assessment submission, evaluates
 * the attendee against the event's completion rule and issues a certificate if
 * eligible. Idempotent (issuance is unique per event+attendee). Runs inside the
 * event's tenant context.
 */
final class IssueCertificateHandler implements OutboxHandler
{
    public function __construct(
        private readonly CertificationService $certification,
    ) {}

    public function handle(OutboxEvent $event): void
    {
        $eventModel = Event::query()->find((int) ($event->payload['event_id'] ?? 0));
        $attendee = Attendee::query()->find((int) ($event->payload['attendee_id'] ?? 0));

        if ($eventModel === null || $attendee === null) {
            return;
        }

        $rule = CompletionRule::query()->where('event_id', $eventModel->getKey())->first();

        if ($rule === null) {
            return;
        }

        if ($this->certification->isEligible($eventModel, $attendee, $rule)) {
            $this->certification->issueFor($eventModel, $attendee);
        }
    }
}
