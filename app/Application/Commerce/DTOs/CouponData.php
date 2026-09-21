<?php

declare(strict_types=1);

namespace App\Application\Commerce\DTOs;

use App\Domain\Commerce\Enums\DiscountType;
use Illuminate\Support\Carbon;

final class CouponData
{
    public function __construct(
        public readonly string $code,
        public readonly DiscountType $discountType,
        public readonly int $discountValue,
        public readonly ?int $maxRedemptions,
        public readonly ?Carbon $startsAt,
        public readonly ?Carbon $endsAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $starts = $data['starts_at'] ?? null;
        $ends = $data['ends_at'] ?? null;
        $max = $data['max_redemptions'] ?? null;

        return new self(
            code: (string) $data['code'],
            discountType: DiscountType::from((string) $data['discount_type']),
            discountValue: (int) $data['discount_value'],
            maxRedemptions: $max !== null && $max !== '' ? (int) $max : null,
            startsAt: is_string($starts) && $starts !== '' ? Carbon::parse($starts) : null,
            endsAt: is_string($ends) && $ends !== '' ? Carbon::parse($ends) : null,
        );
    }
}
