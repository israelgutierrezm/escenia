<?php

declare(strict_types=1);

use App\Domain\Registration\Models\Attendee;
use App\Domain\Registration\Models\Contact;
use App\Domain\Registration\Models\Registration;
use Laravel\Sanctum\Sanctum;

it('exposes the public form and registers an attendee, returning a token once', function () {
    [, , $event] = makeWebinarHost();

    // Public form is visible without auth or a tenant header.
    $this->getJson("/api/v1/events/{$event->ulid}/registration")
        ->assertOk()
        ->assertJsonPath('data.event.id', $event->ulid)
        ->assertJsonPath('data.form.is_open', true);

    $response = $this->postJson("/api/v1/events/{$event->ulid}/register", [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ])->assertCreated();

    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    // The token is never echoed by the attendee resource, only once at the top level.
    $response->assertJsonMissingPath('data.attendee.token');

    $this->assertDatabaseHas('contacts', ['email' => 'ada@example.com']);
    $this->assertDatabaseHas('attendees', ['email' => 'ada@example.com']);
    expect(Attendee::query()->withoutGlobalScopes()->first()->join_token_hash)
        ->toBe(hash('sha256', $token));
});

it('derives the tenant from the event, not the request', function () {
    [, $tenant, $event] = makeWebinarHost();

    $this->postJson("/api/v1/events/{$event->ulid}/register", [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ])->assertCreated();

    expect(Contact::query()->withoutGlobalScopes()->first()->tenant_id)->toBe($tenant->getKey());
    expect(Registration::query()->withoutGlobalScopes()->first()->tenant_id)->toBe($tenant->getKey());
});

it('rejects registration when the form is closed', function () {
    [, , $event, $headers] = makeWebinarHost();

    $this->withHeaders($headers)
        ->putJson("/api/v1/events/{$event->ulid}/registration-form", ['is_open' => false, 'fields' => []])
        ->assertOk();

    $this->postJson("/api/v1/events/{$event->ulid}/register", ['name' => 'Late', 'email' => 'late@example.com'])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'registration_closed');
});

it('rejects registration for an event with no form', function () {
    [, , $event] = makeEventOwner('No Form Event');

    $this->postJson("/api/v1/events/{$event->ulid}/register", ['name' => 'Nobody', 'email' => 'no@example.com'])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'registration_closed');
});

it('is idempotent per email: reuses the contact and rotates the token', function () {
    [, , $event] = makeWebinarHost();

    $first = $this->postJson("/api/v1/events/{$event->ulid}/register", ['name' => 'Repeat', 'email' => 'repeat@example.com'])
        ->assertCreated()->json('data.token');

    $second = $this->postJson("/api/v1/events/{$event->ulid}/register", ['name' => 'Repeat', 'email' => 'repeat@example.com'])
        ->assertCreated()->json('data.token');

    expect(Contact::query()->withoutGlobalScopes()->where('email', 'repeat@example.com')->count())->toBe(1);
    expect(Attendee::query()->withoutGlobalScopes()->count())->toBe(1);
    expect($second)->not->toBe($first);

    // Old token no longer resolves; the new one does.
    $this->withHeaders(['X-Attendee-Token' => $first])->postJson('/api/v1/attend/presence/join')->assertUnauthorized();
    $this->withHeaders(['X-Attendee-Token' => $second])->postJson('/api/v1/attend/presence/join')->assertOk();
});

it('validates the registration payload', function () {
    [, , $event] = makeWebinarHost();

    $this->postJson("/api/v1/events/{$event->ulid}/register", ['name' => '', 'email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'validation_failed');
});

it('lets the host list registrants but forbids other tenants', function () {
    [, , $event, $headers] = makeWebinarHost();
    $this->postJson("/api/v1/events/{$event->ulid}/register", ['name' => 'Reg One', 'email' => 'one@example.com'])->assertCreated();

    $this->withHeaders($headers)
        ->getJson("/api/v1/events/{$event->ulid}/registrations")
        ->assertOk()
        ->assertJsonPath('data.0.contact.email', 'one@example.com');

    // Another tenant cannot see this event's registrants (isolated -> 404).
    [$other] = registerTenantOwner(tenantName: 'Other Co');
    Sanctum::actingAs($other);
    $this->withHeader('X-Tenant-Id', $other->tenants()->first()->ulid)
        ->getJson("/api/v1/events/{$event->ulid}/registrations")
        ->assertNotFound();
});

it('validates registration form fields on save', function () {
    [, , $event, $headers] = makeWebinarHost();

    $this->withHeaders($headers)
        ->putJson("/api/v1/events/{$event->ulid}/registration-form", [
            'is_open' => true,
            'fields' => [['key' => 'Bad Key!', 'label' => 'Company', 'type' => 'text']],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error_code', 'validation_failed');

    $this->withHeaders($headers)
        ->putJson("/api/v1/events/{$event->ulid}/registration-form", [
            'is_open' => true,
            'fields' => [['key' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => true]],
        ])
        ->assertOk()
        ->assertJsonPath('data.fields.0.key', 'company');
});
