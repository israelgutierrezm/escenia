<?php

declare(strict_types=1);

namespace App\Domain\Automation\Support;

use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\ValueObjects\WebhookEndpoint;
use App\Domain\Automation\ValueObjects\WebhookSecret;

/**
 * Signs outbound automation webhooks with the Standard Webhooks scheme
 * (https://www.standardwebhooks.com, ADR-038): `webhook-id`, `webhook-timestamp`
 * and `webhook-signature` = space-separated `v1,<base64 HMAC-SHA256 of
 * "id.timestamp.body">`, one per active secret. The id is stable across retries
 * so receivers can deduplicate; the timestamp is the attempt's, so they can
 * reject replays. A pre-ADR-038 secret keeps signing `X-Escenia-Signature`
 * (hex HMAC of the body) until it is rotated.
 */
final class WebhookSigner
{
    /**
     * Stable across retries and outbox replays: a run is unique per automation
     * and outbox event, and its steps never change.
     */
    public static function messageId(AutomationRun $run, AutomationStep $step): string
    {
        return "msg_{$run->ulid}_{$step->position}";
    }

    /**
     * @return array<string, string>
     */
    public function headers(WebhookEndpoint $endpoint, string $messageId, int $timestamp, string $body): array
    {
        $headers = ['webhook-id' => $messageId, 'webhook-timestamp' => (string) $timestamp];

        $signatures = array_map(
            static fn (WebhookSecret $secret): string => 'v1,'.self::sign($secret, $messageId, $timestamp, $body),
            $endpoint->signingSecrets($timestamp),
        );

        if ($signatures !== []) {
            $headers['webhook-signature'] = implode(' ', $signatures);
        }

        $legacy = $endpoint->legacySecret($timestamp);

        if ($legacy !== null) {
            $headers['X-Escenia-Signature'] = hash_hmac('sha256', $body, $legacy->key());
        }

        return $headers;
    }

    public static function sign(WebhookSecret $secret, string $messageId, int $timestamp, string $body): string
    {
        return base64_encode(hash_hmac('sha256', "{$messageId}.{$timestamp}.{$body}", $secret->key(), true));
    }
}
