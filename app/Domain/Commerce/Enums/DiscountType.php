<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Enums;

/**
 * How a coupon's value reduces an order subtotal: a percentage (1-100) or a
 * fixed amount in minor units.
 */
enum DiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    /**
     * Discount (minor units) this type applies to a subtotal, never exceeding it.
     */
    public function discountFor(int $subtotalMinor, int $value): int
    {
        $discount = match ($this) {
            self::Percent => intdiv($subtotalMinor * $value, 100),
            self::Fixed => $value,
        };

        return max(0, min($discount, $subtotalMinor));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}
