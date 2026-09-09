<?php

declare(strict_types=1);

namespace App\Application\Education\Actions;

use App\Application\Education\CertificationService;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Education\Models\CompletionRule;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Registration\Models\Attendee;

/**
 * Host-triggered sweep that issues certificates to every eligible attendee of an
 * event — the path for attendance-only certification (no assessment submission
 * to react to). Idempotent.
 */
final class IssueCertificatesAction
{
    public function __construct(
        private readonly CertificationService $certification,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return int number of newly issued certificates
     */
    public function execute(Event $event, User $actor): int
    {
        $rule = CompletionRule::query()->where('event_id', $event->getKey())->first();

        if ($rule === null) {
            return 0;
        }

        $issued = 0;

        Attendee::query()
            ->where('event_id', $event->getKey())
            ->chunkById(200, function ($attendees) use ($event, $rule, &$issued): void {
                foreach ($attendees as $attendee) {
                    if (! $this->certification->isEligible($event, $attendee, $rule)) {
                        continue;
                    }

                    if ($this->certification->issueFor($event, $attendee)->wasRecentlyCreated) {
                        $issued++;
                    }
                }
            });

        $this->audit->log('education.certificates.issued', actor: $actor, tenant: $event->tenant, auditable: $event, context: [
            'count' => $issued,
        ]);

        return $issued;
    }
}
