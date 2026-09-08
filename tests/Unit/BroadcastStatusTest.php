<?php

declare(strict_types=1);

use App\Domain\Broadcasting\Enums\BroadcastStatus;

it('allows only the declared broadcast transitions', function () {
    expect(BroadcastStatus::Idle->canTransitionTo(BroadcastStatus::Live))->toBeTrue()
        ->and(BroadcastStatus::Starting->canTransitionTo(BroadcastStatus::Live))->toBeTrue()
        ->and(BroadcastStatus::Live->canTransitionTo(BroadcastStatus::Ended))->toBeTrue()
        ->and(BroadcastStatus::Live->canTransitionTo(BroadcastStatus::Failed))->toBeTrue()
        ->and(BroadcastStatus::Ended->canTransitionTo(BroadcastStatus::Live))->toBeFalse();
});

it('marks terminal broadcast states', function () {
    expect(BroadcastStatus::Ended->isTerminal())->toBeTrue()
        ->and(BroadcastStatus::Failed->isTerminal())->toBeTrue()
        ->and(BroadcastStatus::Live->isTerminal())->toBeFalse();
});
