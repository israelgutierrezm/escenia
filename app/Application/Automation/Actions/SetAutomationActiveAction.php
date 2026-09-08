<?php

declare(strict_types=1);

namespace App\Application\Automation\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Automation\Models\Automation;
use App\Domain\Identity\Models\User;

/**
 * Activates or deactivates an automation. A deactivated automation no longer
 * starts new runs (in-flight runs are unaffected).
 */
final class SetAutomationActiveAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, Automation $automation, bool $active): Automation
    {
        $automation->forceFill(['is_active' => $active])->save();

        $this->audit->log(
            'automation.'.($active ? 'activated' : 'deactivated'),
            actor: $actor,
            tenant: $automation->tenant,
            auditable: $automation,
        );

        return $automation;
    }
}
