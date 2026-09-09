<?php

declare(strict_types=1);

namespace App\Application\Education\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Education\Models\CompletionRule;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;

/**
 * Creates or updates an event's completion rule (min watched time and/or
 * requiring the assessment). One rule per event.
 */
final class SaveCompletionRuleAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, ?int $minWatchSeconds, bool $requireAssessment): CompletionRule
    {
        $rule = CompletionRule::query()->updateOrCreate(
            ['event_id' => $event->getKey()],
            [
                'min_watch_seconds' => $minWatchSeconds,
                'require_assessment' => $requireAssessment,
            ],
        );

        $this->audit->log('education.completion_rule.saved', actor: $actor, tenant: $event->tenant, auditable: $rule);

        return $rule;
    }
}
