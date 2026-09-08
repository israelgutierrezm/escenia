<?php

declare(strict_types=1);

use App\Domain\Commerce\Enums\OrderStatus;

it('allows only the declared order transitions', function () {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Paid))->toBeTrue()
        ->and(OrderStatus::Pending->canTransitionTo(OrderStatus::Canceled))->toBeTrue()
        ->and(OrderStatus::Paid->canTransitionTo(OrderStatus::Refunded))->toBeTrue()
        ->and(OrderStatus::Pending->canTransitionTo(OrderStatus::Refunded))->toBeFalse()
        ->and(OrderStatus::Paid->canTransitionTo(OrderStatus::Canceled))->toBeFalse();
});

it('marks terminal order states', function () {
    expect(OrderStatus::Refunded->isTerminal())->toBeTrue()
        ->and(OrderStatus::Canceled->isTerminal())->toBeTrue()
        ->and(OrderStatus::Pending->isTerminal())->toBeFalse()
        ->and(OrderStatus::Paid->isTerminal())->toBeFalse();
});
