<?php

declare(strict_types=1);

namespace App\Application\Automation\DTOs;

use App\Domain\Automation\Models\AutomationStep;
use SensitiveParameter;

/**
 * A webhook signing secret just issued for a step (on creation or rotation).
 * This is the only time it leaves the server: the API returns it once.
 */
final class IssuedWebhookSecret
{
    public function __construct(
        public readonly AutomationStep $step,
        #[SensitiveParameter] public readonly string $plainSecret,
    ) {}
}
