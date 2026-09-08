<?php

declare(strict_types=1);

use App\Domain\Engagement\Enums\PollStatus;

it('allows only the declared poll transitions', function () {
    expect(PollStatus::Draft->canTransitionTo(PollStatus::Open))->toBeTrue()
        ->and(PollStatus::Open->canTransitionTo(PollStatus::Closed))->toBeTrue()
        ->and(PollStatus::Draft->canTransitionTo(PollStatus::Closed))->toBeFalse()
        ->and(PollStatus::Closed->canTransitionTo(PollStatus::Open))->toBeFalse()
        ->and(PollStatus::Open->canTransitionTo(PollStatus::Draft))->toBeFalse();
});

it('marks a closed poll terminal', function () {
    expect(PollStatus::Closed->isTerminal())->toBeTrue()
        ->and(PollStatus::Draft->isTerminal())->toBeFalse()
        ->and(PollStatus::Open->isTerminal())->toBeFalse();
});
