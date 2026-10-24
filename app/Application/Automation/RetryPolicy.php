<?php

declare(strict_types=1);

namespace App\Application\Automation;

use Illuminate\Contracts\Config\Repository;

/**
 * How long to wait before attempting a step again (config/automation.php,
 * ADR-038). Two schedules: one for a step that reported a transient failure
 * (`retry`), one for a step that threw. Both share the run's attempt counter.
 */
final class RetryPolicy
{
    /** @var list<int> */
    private readonly array $outcomeDelays;

    /** @var list<int> */
    private readonly array $errorDelays;

    private readonly int $maxRetryAfter;

    private readonly float $jitter;

    public function __construct(Repository $config)
    {
        $this->outcomeDelays = self::delays($config->get('automation.retries.webhook'));
        $this->errorDelays = self::delays($config->get('automation.retries.error'));
        $this->maxRetryAfter = (int) $config->get('automation.retries.max_retry_after', 18000);
        $this->jitter = max(0.0, (float) $config->get('automation.retries.jitter', 0.1));
    }

    public function maxAttempts(bool $thrown): int
    {
        return count($thrown ? $this->errorDelays : $this->outcomeDelays) + 1;
    }

    /**
     * Seconds to wait after failed attempt number `$attempt` (1-based), or
     * null when no attempts are left.
     */
    public function delayAfter(int $attempt, bool $thrown, ?int $retryAfterSeconds = null): ?int
    {
        $base = ($thrown ? $this->errorDelays : $this->outcomeDelays)[$attempt - 1] ?? null;

        if ($base === null) {
            return null;
        }

        $delay = max($base, min(max(0, $retryAfterSeconds ?? 0), $this->maxRetryAfter));

        return $delay + random_int(0, (int) floor($delay * $this->jitter));
    }

    /**
     * @return list<int>
     */
    private static function delays(mixed $values): array
    {
        return is_array($values) ? array_values(array_map('intval', $values)) : [];
    }
}
