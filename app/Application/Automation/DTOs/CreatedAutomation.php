<?php

declare(strict_types=1);

namespace App\Application\Automation\DTOs;

use App\Domain\Automation\Models\Automation;

/**
 * A new automation and the signing secrets issued for its webhook steps.
 */
final class CreatedAutomation
{
    /**
     * @param  list<IssuedWebhookSecret>  $webhookSecrets
     */
    public function __construct(
        public readonly Automation $automation,
        public readonly array $webhookSecrets,
    ) {}
}
