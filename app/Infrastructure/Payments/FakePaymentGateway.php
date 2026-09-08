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
use Illuminate\Support\Str;

/**
 * Deterministic, network-free gateway for local dev and tests. Its "webhook" is
 * a small JSON body `{reference, outcome}`; if the account has a webhook secret
 * it is checked as a shared secret. No real money ever moves.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public function createIntent(Order $order, ?PaymentAccount $account): PaymentIntentResult
    {
        $reference = 'fake_pi_'.strtolower((string) Str::ulid());

        return new PaymentIntentResult(
            reference: $reference,
            status: PaymentStatus::Pending,
            clientSecret: 'fake_secret_'.$reference,
        );
    }

    public function parseWebhook(string $payload, string $signature, PaymentAccount $account): GatewayEvent
    {
        if ($account->webhook_secret !== null && ! hash_equals($account->webhook_secret, $signature)) {
            throw new WebhookVerificationException;
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($payload, true) ?: [];
        $reference = isset($data['reference']) ? (string) $data['reference'] : '';

        if ($reference === '') {
            throw new WebhookVerificationException('Missing payment reference.');
        }

        $status = match ((string) ($data['outcome'] ?? 'succeeded')) {
            'failed' => PaymentStatus::Failed,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Succeeded,
        };

        return new GatewayEvent($reference, $status);
    }
}
