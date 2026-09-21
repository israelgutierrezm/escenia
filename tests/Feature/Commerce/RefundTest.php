<?php

declare(strict_types=1);

use App\Domain\Commerce\Models\Order;

/**
 * Creates a ticket for the event (self-contained so this file runs in isolation).
 *
 * @param  array<string, string>  $headers
 */
function makeRefundTicket(string $eventUlid, array $headers, int $amountMinor = 5000): string
{
    return test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/commerce/tickets", [
            'name' => 'General Admission',
            'amount_minor' => $amountMinor,
            'currency' => 'USD',
            'capacity' => null,
        ])
        ->assertCreated()
        ->json('data.id');
}

/**
 * Checks out `quantity` tickets and pays the order via the fake gateway webhook.
 * Returns the order's public id.
 *
 * @param  array<string, string>  $headers
 * @param  array{id: string}  $account
 */
function checkoutAndPay(string $eventUlid, array $headers, array $account, string $ticket, int $quantity = 2): string
{
    $checkout = test()->postJson("/api/v1/events/{$eventUlid}/checkout", [
        'buyer_name' => 'Bob Buyer',
        'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => $quantity]],
    ])->assertCreated();

    test()->withHeaders(['X-Fake-Signature' => 'whsec_test'])
        ->postJson("/api/v1/checkout/webhooks/{$account['id']}", [
            'reference' => $checkout->json('data.payment.reference'),
            'outcome' => 'succeeded',
        ])->assertOk();

    return $checkout->json('data.order.id');
}

it('lets the host refund a paid order, releasing stock (idempotently)', function () {
    [, , $event, $headers, $account] = makeCommerceHost();
    $ticket = makeRefundTicket($event->ulid, $headers);
    $orderId = checkoutAndPay($event->ulid, $headers, $account, $ticket, 2);

    $this->assertDatabaseHas('tickets', ['ulid' => $ticket, 'sold_count' => 2]);

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/orders/{$orderId}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded');

    // Stock released and payment marked refunded.
    $this->assertDatabaseHas('tickets', ['ulid' => $ticket, 'sold_count' => 0]);
    $this->assertDatabaseHas('payments', ['status' => 'refunded']);

    // The revenue report reflects the refund.
    $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/commerce/revenue")
        ->assertOk()
        ->assertJsonPath('data.refunded_minor', 10000)
        ->assertJsonPath('data.net_minor', 0);

    // Refunding again is a no-op (still refunded).
    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/orders/{$orderId}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', 'refunded');

    $this->assertDatabaseHas('tickets', ['ulid' => $ticket, 'sold_count' => 0]);
});

it('rejects refunding an unpaid order', function () {
    [, , $event, $headers] = makeCommerceHost();
    $ticket = makeRefundTicket($event->ulid, $headers);

    $orderId = $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Bob Buyer',
        'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 1]],
    ])->assertCreated()->json('data.order.id');

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/orders/{$orderId}/refund")
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'invalid_order_transition');

    expect(Order::query()->withoutGlobalScopes()->firstOrFail()->status->value)->toBe('pending');
});
