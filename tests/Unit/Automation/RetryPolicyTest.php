<?php

declare(strict_types=1);

use App\Application\Automation\RetryPolicy;
use Illuminate\Config\Repository;

function retryPolicy(float $jitter = 0.0): RetryPolicy
{
    return new RetryPolicy(new Repository(['automation' => ['retries' => [
        'webhook' => [60, 300, 1800, 7200, 18000],
        'error' => [60, 300, 1800],
        'max_retry_after' => 18000,
        'jitter' => $jitter,
    ]]]));
}

it('backs off on each attempt and stops when the schedule runs out', function () {
    $policy = retryPolicy();

    expect(array_map(fn (int $attempt): ?int => $policy->delayAfter($attempt, false), [1, 2, 3, 4, 5, 6]))
        ->toBe([60, 300, 1800, 7200, 18000, null])
        ->and($policy->maxAttempts(false))->toBe(6)
        ->and($policy->maxAttempts(true))->toBe(4)
        ->and($policy->delayAfter(4, true))->toBeNull();
});

it('honours Retry-After, capped, but never waits less than the schedule', function () {
    $policy = retryPolicy();

    expect($policy->delayAfter(1, false, 900))->toBe(900)
        ->and($policy->delayAfter(1, false, 86_400))->toBe(18000)
        ->and($policy->delayAfter(3, false, 10))->toBe(1800);
});

it('adds at most 10 % jitter', function () {
    $policy = retryPolicy(0.1);

    foreach (range(1, 50) as $ignored) {
        expect($policy->delayAfter(2, false))->toBeGreaterThanOrEqual(300)->toBeLessThanOrEqual(330);
    }
});
