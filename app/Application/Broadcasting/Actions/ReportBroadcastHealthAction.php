<?php

declare(strict_types=1);

namespace App\Application\Broadcasting\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Broadcasting\Enums\BroadcastHealth;
use App\Domain\Broadcasting\Events\BroadcastHealthChanged;
use App\Domain\Broadcasting\Models\BroadcastSession;
use App\Domain\Identity\Models\User;

/**
 * Records a health update for a broadcast (from a provider webhook or producer).
 */
final class ReportBroadcastHealthAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(BroadcastSession $broadcast, User $actor, BroadcastHealth $health): BroadcastSession
    {
        $from = $broadcast->health;

        if ($from === $health) {
            return $broadcast;
        }

        $broadcast->update(['health' => $health]);

        BroadcastHealthChanged::dispatch($broadcast, $from, $health);

        $this->audit->log('broadcast.health', actor: $actor, tenant: $broadcast->tenant, auditable: $broadcast, context: [
            'from' => $from->value,
            'to' => $health->value,
        ]);

        return $broadcast;
    }
}
