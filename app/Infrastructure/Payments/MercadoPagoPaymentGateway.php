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
 * Mercado Pago adapter (hosted checkout). Creates a preference and returns its
 * `init_point` for redirect; the payment is matched back by the order ULID
 * carried as `external_reference`. Webhook notifications are verified and then
 * the payment is fetched to read its real status. Not integration-tested against
 * Mercado Pago (see technical-debt); the exact signature scheme must be
 * confirmed before production. The SDK never enters the domain.
 */
final class MercadoPagoPaymentGateway implements PaymentGateway
{
    private const API = 'https://api.mercadopago.com';

    public function createIntent(Order $order, ?PaymentAccount $account): PaymentIntentResult
    {
        $token = $this->accessToken($account);

        // The API expects a decimal major-unit amount; format at the boundary
        // only (the domain stays in integer minor units).
        $unitPrice = round($order->total_minor / 100, 2);

        $response = Http::withToken($token)
            ->post(self::API.'/checkout/preferences', [
                'items' => [[
                    'title' => 'Order '.$order->ulid,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'currency_id' => $order->currency,
                ]],
                'external_reference' => $order->ulid,
            ])
            ->throw();

        return new PaymentIntentResult(
            reference: $order->ulid,
            status: PaymentStatus::Pending,
            redirectUrl: (string) $response->json('init_point'),
        );
    }

    public function parseWebhook(string $payload, string $signature, PaymentAccount $account): GatewayEvent
    {
        $token = $this->accessToken($account);
        $this->verifySignature($payload, $signature, (string) ($account->webhook_secret ?? ''));

        /** @var array<string, mixed> $body */
        $body = json_decode($payload, true) ?: [];
        $paymentId = (string) (is_array($body['data'] ?? null) ? ($body['data']['id'] ?? '') : '');

        if ($paymentId === '') {
            throw new WebhookVerificationException('Missing payment id.');
        }

        $payment = Http::withToken($token)
            ->get(self::API.'/v1/payments/'.$paymentId)
            ->throw();

        $status = match ((string) $payment->json('status')) {
            'approved' => PaymentStatus::Succeeded,
            'refunded', 'charged_back' => PaymentStatus::Refunded,
            'rejected', 'cancelled' => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        return new GatewayEvent((string) $payment->json('external_reference'), $status);
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

        $provided = $parts['v1'] ?? '';
        $expected = hash_hmac('sha256', $payload, $secret);

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            throw new WebhookVerificationException;
        }
    }

    private function accessToken(?PaymentAccount $account): string
    {
        $token = (string) ($account?->credentials['access_token'] ?? '');

        if ($token === '') {
            throw new WebhookVerificationException('Mercado Pago account is not configured.');
        }

        return $token;
    }
}
