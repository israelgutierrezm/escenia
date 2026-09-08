<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;

it('creates and lists tickets for the host', function () {
    [, , $event, $headers] = makeCommerceHost();

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/tickets", [
            'name' => 'VIP', 'amount_minor' => 25000, 'currency' => 'USD', 'compare_at_minor' => 30000, 'capacity' => 50,
        ])
        ->assertCreated()
        ->assertJsonPath('data.price.minor_units', 25000)
        ->assertJsonPath('data.compare_at.minor_units', 30000)
        ->assertJsonPath('data.remaining', 50)
        ->assertJsonPath('data.on_sale', true);

    $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/commerce/tickets")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('requires auth to create tickets', function () {
    $this->postJson('/api/v1/events/any/commerce/tickets', ['name' => 'X', 'amount_minor' => 1, 'currency' => 'USD'])
        ->assertUnauthorized();
});

it('isolates tickets from other tenants', function () {
    [, , $event] = makeCommerceHost();

    [$other] = registerTenantOwner(tenantName: 'Other Co');
    Sanctum::actingAs($other);
    $this->withHeader('X-Tenant-Id', $other->tenants()->first()->ulid)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/tickets", ['name' => 'X', 'amount_minor' => 1, 'currency' => 'USD'])
        ->assertNotFound();
});

it('validates ticket input', function () {
    [, , $event, $headers] = makeCommerceHost();

    $this->withHeaders($headers)
        ->postJson("/api/v1/events/{$event->ulid}/commerce/tickets", ['name' => '', 'amount_minor' => -5, 'currency' => 'US'])
        ->assertStatus(422);
});
