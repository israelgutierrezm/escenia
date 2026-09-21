<?php

declare(strict_types=1);

/**
 * @param  array<string, string>  $headers
 */
function makeCouponTicket(string $eventUlid, array $headers, int $amountMinor = 5000): string
{
    return test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/commerce/tickets", [
            'name' => 'General Admission',
            'amount_minor' => $amountMinor,
            'currency' => 'USD',
            'capacity' => null,
        ])->assertCreated()->json('data.id');
}

/**
 * @param  array<string, string>  $headers
 * @param  array<string, mixed>  $payload
 */
function createCoupon(string $eventUlid, array $headers, array $payload): string
{
    return test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/commerce/coupons", $payload)
        ->assertCreated()->json('data.id');
}

it('lets a buyer redeem a percent coupon, counted only on payment', function () {
    [, , $event, $headers, $account] = makeCommerceHost();
    $ticket = makeCouponTicket($event->ulid, $headers, 5000);
    createCoupon($event->ulid, $headers, ['code' => 'save20', 'discount_type' => 'percent', 'discount_value' => 20]);

    $checkout = $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Bob Buyer',
        'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 2]], // 10000
        'coupon_code' => 'save20', // case-insensitive
    ])->assertCreated();

    $checkout->assertJsonPath('data.order.subtotal.minor_units', 10000)
        ->assertJsonPath('data.order.discount.minor_units', 2000)
        ->assertJsonPath('data.order.total.minor_units', 8000);

    // Not counted until the order is paid.
    $this->assertDatabaseHas('coupons', ['code' => 'SAVE20', 'redeemed_count' => 0]);

    $this->withHeaders(['X-Fake-Signature' => 'whsec_test'])
        ->postJson("/api/v1/checkout/webhooks/{$account['id']}", [
            'reference' => $checkout->json('data.payment.reference'),
            'outcome' => 'succeeded',
        ])->assertOk();

    $this->assertDatabaseHas('coupons', ['code' => 'SAVE20', 'redeemed_count' => 1]);
});

it('applies a fixed discount capped at the subtotal', function () {
    [, , $event, $headers] = makeCommerceHost();
    $ticket = makeCouponTicket($event->ulid, $headers, 5000);
    createCoupon($event->ulid, $headers, ['code' => 'FLAT', 'discount_type' => 'fixed', 'discount_value' => 30000]);

    $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Bob Buyer',
        'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 1]], // 5000
        'coupon_code' => 'FLAT',
    ])->assertCreated()
        ->assertJsonPath('data.order.discount.minor_units', 5000) // capped at subtotal
        ->assertJsonPath('data.order.total.minor_units', 0);
});

it('rejects an unknown coupon and a deactivated one', function () {
    [, , $event, $headers] = makeCommerceHost();
    $ticket = makeCouponTicket($event->ulid, $headers);

    $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Bob Buyer',
        'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 1]],
        'coupon_code' => 'NOPE',
    ])->assertStatus(422)->assertJsonPath('error_code', 'coupon_not_applicable');

    $couponId = createCoupon($event->ulid, $headers, ['code' => 'OLD', 'discount_type' => 'percent', 'discount_value' => 10]);
    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/coupons/{$couponId}/deactivate")
        ->assertOk()->assertJsonPath('data.is_active', false);

    $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Bob Buyer',
        'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 1]],
        'coupon_code' => 'OLD',
    ])->assertStatus(422)->assertJsonPath('error_code', 'coupon_not_applicable');
});

it('rejects a duplicate coupon code for the same event', function () {
    [, , $event, $headers] = makeCommerceHost();
    createCoupon($event->ulid, $headers, ['code' => 'DUP', 'discount_type' => 'percent', 'discount_value' => 10]);

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/coupons", ['code' => 'dup', 'discount_type' => 'percent', 'discount_value' => 15])
        ->assertStatus(422)->assertJsonPath('error_code', 'coupon_not_applicable');
});

it('stops honoring a coupon once its redemption limit is reached', function () {
    [, , $event, $headers, $account] = makeCommerceHost();
    $ticket = makeCouponTicket($event->ulid, $headers, 5000);
    createCoupon($event->ulid, $headers, ['code' => 'ONCE', 'discount_type' => 'percent', 'discount_value' => 10, 'max_redemptions' => 1]);

    // Redeem it once (checkout + pay).
    $reference = $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Bob Buyer', 'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 1]], 'coupon_code' => 'ONCE',
    ])->assertCreated()->json('data.payment.reference');

    $this->withHeaders(['X-Fake-Signature' => 'whsec_test'])
        ->postJson("/api/v1/checkout/webhooks/{$account['id']}", ['reference' => $reference, 'outcome' => 'succeeded'])
        ->assertOk();

    // A second buyer can no longer use it.
    $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Sue Buyer', 'buyer_email' => 'sue@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 1]], 'coupon_code' => 'ONCE',
    ])->assertStatus(422)->assertJsonPath('error_code', 'coupon_not_applicable');
});
