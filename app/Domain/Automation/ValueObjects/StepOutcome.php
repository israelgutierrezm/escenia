<?php

declare(strict_types=1);

namespace App\Domain\Automation\ValueObjects;

use App\Domain\Automation\Enums\StepOutcomeKind;
use Carbon\CarbonInterface;

/**
 * The result of running one automation step. `summary` (and `details`) are
 * recorded on the run's log; handlers report what happened and the executor
 * owns the retry policy (ADR-038).
 */
final class StepOutcome
{
    /**
     * @param  array<string, scalar|null>  $details
     */
    private function __construct(
        public readonly StepOutcomeKind $kind,
        public readonly string $summary,
        public readonly ?CarbonInterface $waitUntil = null,
        public readonly ?int $retryAfterSeconds = null,
        public readonly array $details = [],
    ) {}

    /**
     * @param  array<string, scalar|null>  $details
     */
    public static function done(string $summary, array $details = []): self
    {
        return new self(StepOutcomeKind::Done, $summary, details: $details);
    }

    /**
     * A permanent failure: the step is not attempted again and the sequence
     * goes on (best-effort steps such as webhooks).
     *
     * @param  array<string, scalar|null>  $details
     */
    public static function failed(string $summary, array $details = []): self
    {
        return new self(StepOutcomeKind::Failed, $summary, details: $details);
    }

    public static function waitUntil(CarbonInterface $at, string $summary): self
    {
        return new self(StepOutcomeKind::Wait, $summary, waitUntil: $at);
    }

    /**
     * A transient failure: the same step is attempted again later, honouring
     * `$retryAfterSeconds` when the other side asked for it.
     *
     * @param  array<string, scalar|null>  $details
     */
    public static function retry(string $summary, ?int $retryAfterSeconds = null, array $details = []): self
    {
        return new self(StepOutcomeKind::Retry, $summary, retryAfterSeconds: $retryAfterSeconds, details: $details);
    }

    public function isWait(): bool
    {
        return $this->kind === StepOutcomeKind::Wait;
    }

    public function isRetry(): bool
    {
        return $this->kind === StepOutcomeKind::Retry;
    }
}
