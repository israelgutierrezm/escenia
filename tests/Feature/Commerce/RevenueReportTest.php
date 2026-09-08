<?php

declare(strict_types=1);

use App\Application\Outbox\DispatchOutboxAction;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, string>  $headers
 * @param  array<string, mixed>  $account
 */
function paidCheckout(string $eventUlid, array $headers, array $account, string $ticketId, int $quantity, string $email): void
{
    $reference = test()->postJson("/api/v1/events/{$eventUlid}/checkout", [
        'buyer_name' => 'Buyer', 'buyer_email' => $email,
        'items' => [['ticket' => $ticketId, 'quantity' => $quantity]],
    ])->assertCreated()->json('data.payment.reference');

    test()->withHeaders(['X-Fake-Signature' => 'whsec_test'])
        ->postJson("/api/v1/checkout/webhooks/{$account['id']}", ['reference' => $reference, 'outcome' => 'succeeded'])
        ->assertOk();
}

it('reports revenue from the paid ledger', function () {
    [, , $event, $headers, $account] = makeCommerceHost();

    $ga = test()->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/commerce/tickets", [
        'name' => 'GA', 'amount_minor' => 5000, 'currency' => 'USD',
    ])->json('data.id');
    $vip = test()->withHeaders($headers)->postJson("/api/v1/events/{$event->ulid}/commerce/tickets", [
        'name' => 'VIP', 'amount_minor' => 20000, 'currency' => 'USD',
    ])->json('data.id');

    paidCheckout($event->ulid, $headers, $account, $ga, 2, 'a@example.com'); // 10000
    paidCheckout($event->ulid, $headers, $account, $vip, 1, 'b@example.com'); // 20000
    app(DispatchOutboxAction::class)->execute();

    $revenue = $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/commerce/revenue")
        ->assertOk()
        ->json('data');

    expect($revenue['currency'])->toBe('USD')
        ->and($revenue['gross_minor'])->toBe(30000)
        ->and($revenue['net_minor'])->toBe(30000)
        ->and($revenue['orders_paid'])->toBe(2)
        ->and($revenue['avg_order_minor'])->toBe(15000);

    $byTicket = collect($revenue['by_ticket'])->keyBy('name');
    expect($byTicket['GA']['units'])->toBe(2)
        ->and($byTicket['GA']['revenue_minor'])->toBe(10000)
        ->and($byTicket['VIP']['revenue_minor'])->toBe(20000);
});

it('requires commerce access and isolates the report', function () {
    [, , $event] = makeCommerceHost();

    [$other] = registerTenantOwner(tenantName: 'Other Co');
    Sanctum::actingAs($other);
    $this->withHeader('X-Tenant-Id', $other->tenants()->first()->ulid)
        ->getJson("/api/v1/events/{$event->ulid}/commerce/revenue")
        ->assertNotFound();
});
