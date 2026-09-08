<?php

declare(strict_types=1);

use App\Application\Outbox\DispatchOutboxAction;
use App\Domain\Commerce\Models\Order;

/**
 * @param  array<string, string>  $headers
 */
function createTicket(string $eventUlid, array $headers, int $amountMinor = 5000, ?int $capacity = null): string
{
    return test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/commerce/tickets", [
            'name' => 'General Admission',
            'amount_minor' => $amountMinor,
            'currency' => 'USD',
            'capacity' => $capacity,
        ])
        ->assertCreated()
        ->json('data.id');
}

it('runs a full paid checkout: order, token, webhook, outbox fulfilment', function () {
    [, , $event, $headers, $account] = makeCommerceHost();
    $ticket = createTicket($event->ulid, $headers);

    // Public lists the active tickets.
    $this->getJson("/api/v1/events/{$event->ulid}/tickets")
        ->assertOk()
        ->assertJsonPath('data.0.price.minor_units', 5000);

    // Public checkout for 2 tickets.
    $checkout = $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Bob Buyer',
        'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 2]],
    ])->assertCreated();

    $checkout->assertJsonPath('data.order.status', 'pending')
        ->assertJsonPath('data.order.total.minor_units', 10000);
    $token = $checkout->json('data.token');
    $reference = $checkout->json('data.payment.reference');

    expect($token)->toBeString()->not->toBeEmpty();

    // The buyer's token works as an attendee credential.
    $this->withHeaders(['X-Attendee-Token' => $token])->postJson('/api/v1/attend/presence/join')->assertOk();

    // Gateway webhook confirms payment (signature = the account webhook secret).
    $this->withHeaders(['X-Fake-Signature' => 'whsec_test'])
        ->postJson("/api/v1/checkout/webhooks/{$account['id']}", ['reference' => $reference, 'outcome' => 'succeeded'])
        ->assertOk();

    $order = Order::query()->withoutGlobalScopes()->firstOrFail();
    expect($order->status->value)->toBe('paid')
        ->and($order->fulfilled_at)->toBeNull(); // not fulfilled until the outbox drains

    // The outbox publishes order.paid -> revenue recorded + order fulfilled.
    expect(app(DispatchOutboxAction::class)->execute())->toBe(1);

    expect($order->fresh()->fulfilled_at)->not->toBeNull();
    $this->assertDatabaseHas('analytics_events', ['name' => 'commerce.order_paid']);

    // Sold count moved on payment, not on checkout.
    $this->assertDatabaseHas('tickets', ['ulid' => $ticket, 'sold_count' => 2]);
});

it('is idempotent to a replayed webhook', function () {
    [, , $event, $headers, $account] = makeCommerceHost();
    $ticket = createTicket($event->ulid, $headers, capacity: 5);

    $reference = $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Bob', 'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 1]],
    ])->json('data.payment.reference');

    $webhook = fn () => $this->withHeaders(['X-Fake-Signature' => 'whsec_test'])
        ->postJson("/api/v1/checkout/webhooks/{$account['id']}", ['reference' => $reference, 'outcome' => 'succeeded']);

    $webhook()->assertOk();
    $webhook()->assertOk(); // replay: no-op

    app(DispatchOutboxAction::class)->execute();

    $this->assertDatabaseHas('tickets', ['ulid' => $ticket, 'sold_count' => 1]);
    expect(Order::query()->withoutGlobalScopes()->where('status', 'paid')->count())->toBe(1);
});

it('rejects a webhook with a bad signature', function () {
    [, , $event, $headers, $account] = makeCommerceHost();
    $ticket = createTicket($event->ulid, $headers);
    $reference = $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Bob', 'buyer_email' => 'bob@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 1]],
    ])->json('data.payment.reference');

    $this->withHeaders(['X-Fake-Signature' => 'wrong'])
        ->postJson("/api/v1/checkout/webhooks/{$account['id']}", ['reference' => $reference, 'outcome' => 'succeeded'])
        ->assertForbidden()
        ->assertJsonPath('error_code', 'webhook_verification_failed');

    expect(Order::query()->withoutGlobalScopes()->where('status', 'paid')->count())->toBe(0);
});

it('rejects checkout for a sold-out ticket', function () {
    [, , $event, $headers] = makeCommerceHost();
    $ticket = createTicket($event->ulid, $headers, capacity: 1);

    $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => 'Greedy', 'buyer_email' => 'g@example.com',
        'items' => [['ticket' => $ticket, 'quantity' => 2]],
    ])->assertStatus(422)->assertJsonPath('error_code', 'checkout_unavailable');
});

it('validates the checkout payload', function () {
    [, , $event] = makeCommerceHost();

    $this->postJson("/api/v1/events/{$event->ulid}/checkout", [
        'buyer_name' => '', 'buyer_email' => 'not-email', 'items' => [],
    ])->assertStatus(422)->assertJsonPath('error_code', 'validation_failed');
});
