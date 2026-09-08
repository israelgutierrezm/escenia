<?php

declare(strict_types=1);

use App\Domain\Events\Enums\EventStatus;

it('allows only the declared lifecycle transitions', function () {
    expect(EventStatus::Draft->canTransitionTo(EventStatus::Scheduled))->toBeTrue()
        ->and(EventStatus::Draft->canTransitionTo(EventStatus::Live))->toBeFalse()
        ->and(EventStatus::Scheduled->canTransitionTo(EventStatus::Live))->toBeTrue()
        ->and(EventStatus::Live->canTransitionTo(EventStatus::Ended))->toBeTrue()
        ->and(EventStatus::Ended->canTransitionTo(EventStatus::Archived))->toBeTrue()
        ->and(EventStatus::Live->canTransitionTo(EventStatus::Draft))->toBeFalse();
});

it('marks terminal states', function () {
    expect(EventStatus::Archived->isTerminal())->toBeTrue()
        ->and(EventStatus::Canceled->isTerminal())->toBeTrue()
        ->and(EventStatus::Draft->isTerminal())->toBeFalse();
});
