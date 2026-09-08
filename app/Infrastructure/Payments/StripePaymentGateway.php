<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments;

use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Exceptions\WebhookVerificationException;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\PaymentAccount;
use App\Domain\Commerce\ValueObjects\GatewayEvent;
use App\Domain\Commerce\ValueObjects\PaymentIntentResult;
use Illuminate\Support\Facades\Http;

/**
 * Stripe adapter. Creates PaymentIntents over the REST API and verifies webhook
 * signatures (HMAC-SHA256 over `timestamp.payload`, per Stripe). The Stripe SDK
 * never enters the domain — only Money and gateway-agnostic Value Objects. Not
 * integration-tested against Stripe (see technical-debt); the `fake` gateway is
 * the default in dev/tests.
 */
final class StripePaymentGateway implements PaymentGateway
{
    private const API = 'https://api.stripe.com/v1';

    public function createIntent(Order $order, ?PaymentAccount $account): PaymentIntentResult
    {
        $secret = $this->secretKey($account);

        $response = Http::withToken($secret)
            ->asForm()
            ->post(self::API.'/payment_intents', [
                'amount' => $order->total_minor,
                'currency' => strtolower($order->currency),
                'metadata[order]' => $order->ulid,
            ])
            ->throw();

        return new PaymentIntentResult(
            reference: (string) $response->json('id'),
            status: PaymentStatus::Pending,
            clientSecret: (string) $response->json('client_secret'),
        );
    }

    public function parseWebhook(string $payload, string $signature, PaymentAccount $account): GatewayEvent
    {
        $this->verifySignature($payload, $signature, (string) ($account->webhook_secret ?? ''));

        /** @var array<string, mixed> $event */
        $event = json_decode($payload, true) ?: [];
        /** @var array<string, mixed> $object */
        $object = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];

        $status = match ((string) ($event['type'] ?? '')) {
            'payment_intent.succeeded' => PaymentStatus::Succeeded,
            'payment_intent.payment_failed' => PaymentStatus::Failed,
            'charge.refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };

        $reference = (string) ($object['payment_intent'] ?? $object['id'] ?? '');

        if ($reference === '') {
            throw new WebhookVerificationException('Missing payment reference.');
        }

        return new GatewayEvent($reference, $status);
    }

    private function verifySignature(string $payload, string $signature, string $secret): void
    {
        if ($secret === '') {
            throw new WebhookVerificationException('No webhook secret configured.');
        }

        $parts = [];
        foreach (explode(',', $signature) as $segment) {
            [$key, $value] = array_pad(explode('=', trim($segment), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $provided = $parts['v1'] ?? '';
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            throw new WebhookVerificationException;
        }
    }

    private function secretKey(?PaymentAccount $account): string
    {
        $secret = (string) ($account?->credentials['secret_key'] ?? '');

        if ($secret === '') {
            throw new WebhookVerificationException('Stripe account is not configured.');
        }

        return $secret;
    }
}
