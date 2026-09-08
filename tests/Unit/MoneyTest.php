<?php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\Money;

it('is created from minor units and an uppercased currency', function () {
    $money = Money::of(1500, 'usd');

    expect($money->minorUnits)->toBe(1500)
        ->and($money->currency)->toBe('USD');
});

it('adds and subtracts within the same currency', function () {
    expect(Money::of(100, 'USD')->add(Money::of(50, 'USD'))->minorUnits)->toBe(150)
        ->and(Money::of(100, 'USD')->subtract(Money::of(30, 'USD'))->minorUnits)->toBe(70);
});

it('rejects mixing currencies', function () {
    Money::of(100, 'USD')->add(Money::of(1, 'EUR'));
})->throws(InvalidArgumentException::class);

it('rejects invalid currency codes', function () {
    new Money(1, 'us');
})->throws(InvalidArgumentException::class);

it('serializes as integer minor units, never a float', function () {
    expect(Money::of(999, 'USD')->toArray())->toBe([
        'minor_units' => 999,
        'currency' => 'USD',
    ]);
});
