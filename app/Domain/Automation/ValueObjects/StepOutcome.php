<?php

declare(strict_types=1);

namespace App\Domain\Automation\ValueObjects;

use Carbon\CarbonInterface;

/**
 * The result of running one automation step: either continue to the next step,
 * or park the run until `waitUntil` (a wait step / sequence delay). `summary` is
 * recorded on the run's log.
 */
final class StepOutcome
{
    private function __construct(
        public readonly ?CarbonInterface $waitUntil,
        public readonly string $summary,
    ) {}

    public static function done(string $summary): self
    {
        return new self(null, $summary);
    }

    public static function waitUntil(CarbonInterface $at, string $summary): self
    {
        return new self($at, $summary);
    }

    public function isWait(): bool
    {
        return $this->waitUntil !== null;
    }
}
