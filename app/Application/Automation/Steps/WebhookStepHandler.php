<?php

declare(strict_types=1);

namespace App\Application\Automation\Steps;

use App\Domain\Automation\Contracts\StepHandler;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\Support\WebhookSigner;
use App\Domain\Automation\ValueObjects\StepOutcome;
use App\Infrastructure\Http\OutboundUrlGuard;
use App\Infrastructure\Http\UnresolvableHostException;
use App\Infrastructure\Http\UnsafeOutboundUrlException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Outbound webhook: POSTs the (public) trigger context to a tenant-configured
 * URL, signed with the Standard Webhooks scheme (ADR-038). Only https, and only
 * through the egress guard: the host must resolve to public addresses, the
 * connection is pinned to the one checked and redirects are not followed — the
 * URL can never reach the internal network (ADR-037).
 *
 * Transient failures (connection errors, a host that does not resolve, 408, 429,
 * 5xx) report `retry` and the executor attempts the step again with the same
 * `webhook-id`; anything else is permanent (`failed`) and the sequence goes on.
 * Logs carry the host only: the URL may itself be a credential.
 */
final class WebhookStepHandler implements StepHandler
{
    public function __construct(
        private readonly OutboundUrlGuard $guard,
        private readonly WebhookSigner $signer,
    ) {}

    public function handle(AutomationRun $run, AutomationStep $step, array $context): StepOutcome
    {
        $endpoint = $step->webhookEndpoint();

        if ($endpoint === null || ! $endpoint->isHttps()) {
            return StepOutcome::failed('webhook skipped (https required)');
        }

        $messageId = WebhookSigner::messageId($run, $step);
        $details = ['webhook_id' => $messageId];

        try {
            $options = $this->guard->pinnedOptions($endpoint->url);
        } catch (UnresolvableHostException) {
            Log::warning('automation.webhook_unresolvable', ['host' => $endpoint->host(), 'run' => $run->ulid]);

            return StepOutcome::retry('webhook_failed (host does not resolve)', details: $details);
        } catch (UnsafeOutboundUrlException) {
            Log::warning('automation.webhook_blocked', ['host' => $endpoint->host(), 'run' => $run->ulid]);

            return StepOutcome::failed('webhook_blocked (destination not allowed)', $details);
        }

        $body = (string) json_encode([
            'trigger' => $context['trigger'] ?? null,
            'run' => $run->ulid,
            'context' => $this->publicContext($context),
        ]);

        try {
            $response = Http::withOptions($options)
                ->connectTimeout(5)
                ->timeout(10)
                ->withHeaders($this->signer->headers($endpoint, $messageId, now()->getTimestamp(), $body))
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
        } catch (Throwable $e) {
            // The exception message can carry the full URL: log its class only.
            Log::warning('automation.webhook_failed', ['host' => $endpoint->host(), 'error' => $e::class]);

            return StepOutcome::retry('webhook_failed (connection)', details: $details);
        }

        $status = $response->status();
        $details['status'] = $status;

        if ($response->successful()) {
            return StepOutcome::done("webhook_sent {$status}", $details);
        }

        if (self::isRetryable($status)) {
            return StepOutcome::retry("webhook_failed {$status}", $this->retryAfterSeconds($response->header('Retry-After')), $details);
        }

        // 3xx (redirects are never followed) and every other 4xx, 410 included.
        return StepOutcome::failed("webhook_failed {$status}", $details);
    }

    /**
     * 408, 429 and 5xx are transient; every other non-2xx is permanent.
     */
    public static function isRetryable(int $status): bool
    {
        return $status === 408 || $status === 429 || ($status >= 500 && $status <= 599);
    }

    /**
     * `Retry-After` as delta-seconds or an HTTP date; null when absent or invalid.
     */
    private function retryAfterSeconds(string $header): ?int
    {
        $header = trim($header);

        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return (int) $header;
        }

        try {
            $at = CarbonImmutable::createFromFormat(DATE_RFC7231, $header);
        } catch (Throwable) {
            return null;
        }

        return $at instanceof CarbonImmutable ? max(0, $at->getTimestamp() - now()->getTimestamp()) : null;
    }

    /**
     * Never send internal keys (prefixed `_`) to an external endpoint.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function publicContext(array $context): array
    {
        return array_filter(
            $context,
            static fn (string $key): bool => ! str_starts_with($key, '_'),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
