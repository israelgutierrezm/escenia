<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use App\Domain\Commerce\Enums\PaymentStatus;

/**
 * A verified, gateway-agnostic webhook event: which payment (by reference) and
 * its new status. The domain never sees the raw SDK payload.
 */
final class GatewayEvent
{
    public function __construct(
        public readonly string $reference,
        public readonly PaymentStatus $status,
    ) {}

    public function isSucceeded(): bool
    {
        return $this->status === PaymentStatus::Succeeded;
    }
}
