<?php

declare(strict_types=1);

/**
 * Obtain an attendee join token the commerce-native way: buy a ticket. The
 * token is issued at checkout, before payment.
 *
 * @param  array<string, string>  $headers
 */
function attendeeViaCheckout(string $eventUlid, array $headers, string $email = 'clicker@example.com'): string
{
    $ticket = test()->withHeaders($headers)
        ->postJson("/api/v1/events/{$eventUlid}/commerce/tickets", ['name' => 'GA', 'amount_minor' => 1000, 'currency' => 'USD'])
        ->json('data.id');

    return test()->postJson("/api/v1/events/{$eventUlid}/checkout", [
        'buyer_name' => 'Clicker', 'buyer_email' => $email,
        'items' => [['ticket' => $ticket, 'quantity' => 1]],
    ])->assertCreated()->json('data.token');
}

it('lets the host publish a CTA and attendees see and click it', function () {
    [, , $event, $headers] = makeCommerceHost();
    $token = attendeeViaCheckout($event->ulid, $headers);

    $ctaId = $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/ctas", [
            'title' => 'Buy the workshop', 'url' => 'https://example.com/buy',
        ])
        ->assertCreated()
        ->assertJsonPath('data.clicks_count', 0)
        ->json('data.id');

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->getJson('/api/v1/attend/ctas')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Buy the workshop');

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->postJson("/api/v1/attend/ctas/{$ctaId}/click")
        ->assertOk()
        ->assertJsonPath('data.clicks_count', 1);

    $this->assertDatabaseHas('analytics_events', ['name' => 'commerce.cta_clicked']);
    $this->assertDatabaseCount('cta_clicks', 1);
});

it('hides not-yet-live CTAs from attendees', function () {
    [, , $event, $headers] = makeCommerceHost();
    $token = attendeeViaCheckout($event->ulid, $headers);

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/ctas", [
            'title' => 'Later', 'starts_at' => now()->addDay()->toIso8601String(),
        ])
        ->assertCreated();

    $this->withHeaders(['X-Attendee-Token' => $token])
        ->getJson('/api/v1/attend/ctas')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
