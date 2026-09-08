<?php

declare(strict_types=1);

use App\Domain\Commerce\Models\PaymentAccount;
use Illuminate\Support\Facades\DB;

it('connects a gateway and never returns its secrets', function () {
    [, , , $headers] = makeCommerceHost();

    $response = $this->withHeaders($headers)
        ->putJson('/api/v1/payment-accounts', [
            'gateway' => 'stripe',
            'display_name' => 'Stripe Live',
            'currency' => 'USD',
            'credentials' => ['secret_key' => 'sk_live_supersecret'],
            'webhook_secret' => 'whsec_abc',
        ])
        ->assertOk();

    $response->assertJsonPath('data.gateway', 'stripe')
        ->assertJsonMissingPath('data.credentials')
        ->assertJsonMissingPath('data.webhook_secret');

    // Stored encrypted (raw column is not the plaintext), but decrypts via cast.
    $account = PaymentAccount::query()->withoutGlobalScopes()->where('gateway', 'stripe')->firstOrFail();
    expect($account->credentials['secret_key'])->toBe('sk_live_supersecret');

    $raw = DB::table('payment_accounts')->where('id', $account->id)->value('credentials');
    expect($raw)->not->toContain('sk_live_supersecret');
});

it('upserts the account per gateway (200, not 201)', function () {
    [, , , $headers] = makeCommerceHost();

    // makeCommerceHost already created the fake account; updating it stays 200.
    $this->withHeaders($headers)
        ->putJson('/api/v1/payment-accounts', [
            'gateway' => 'fake', 'display_name' => 'Renamed', 'currency' => 'EUR', 'credentials' => [],
        ])
        ->assertOk()
        ->assertJsonPath('data.display_name', 'Renamed');

    expect(PaymentAccount::query()->withoutGlobalScopes()->where('gateway', 'fake')->count())->toBe(1);
});
