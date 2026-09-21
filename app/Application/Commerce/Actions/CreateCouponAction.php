<?php

declare(strict_types=1);

namespace App\Application\Commerce\Actions;

use App\Application\Commerce\DTOs\CouponData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Commerce\Enums\DiscountType;
use App\Domain\Commerce\Exceptions\CouponNotApplicableException;
use App\Domain\Commerce\Models\Coupon;
use App\Domain\Events\Models\Event;

/**
 * Creates a discount coupon for an event. Codes are normalized (upper-cased,
 * trimmed) and unique per event; percentage discounts are bounded to 1-100.
 */
final class CreateCouponAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, CouponData $data): Coupon
    {
        $code = strtoupper(trim($data->code));

        if ($code === '') {
            throw new CouponNotApplicableException('A coupon code is required.');
        }

        if ($data->discountType === DiscountType::Percent && ($data->discountValue < 1 || $data->discountValue > 100)) {
            throw new CouponNotApplicableException('A percentage discount must be between 1 and 100.');
        }

        if ($data->discountValue < 1) {
            throw new CouponNotApplicableException('The discount value must be positive.');
        }

        $exists = Coupon::query()
            ->where('event_id', $event->getKey())
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw new CouponNotApplicableException('A coupon with this code already exists for the event.');
        }

        /** @var Coupon $coupon */
        $coupon = Coupon::query()->create([
            'tenant_id' => $event->tenant_id,
            'event_id' => $event->getKey(),
            'code' => $code,
            'discount_type' => $data->discountType->value,
            'discount_value' => $data->discountValue,
            'max_redemptions' => $data->maxRedemptions,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'is_active' => true,
        ]);

        $this->audit->log('commerce.coupon.created', tenant: $event->tenant, auditable: $coupon, context: [
            'code' => $code,
        ]);

        return $coupon;
    }
}
