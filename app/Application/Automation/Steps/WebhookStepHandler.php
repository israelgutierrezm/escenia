<?php

declare(strict_types=1);

namespace App\Application\Automation\Steps;

use App\Domain\Automation\Contracts\StepHandler;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\ValueObjects\StepOutcome;
use App\Infrastructure\Http\OutboundUrlGuard;
use App\Infrastructure\Http\UnsafeOutboundUrlException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Outbound webhook: POSTs the (public) trigger context to a tenant-configured
 * URL, HMAC-signed with the step secret so the receiver can verify authenticity.
 * Only https, and only through the egress guard: the host must resolve to
 * public addresses, the connection is pinned to the one checked and redirects
 * are not followed — the URL can never reach the internal network (ADR-037).
 * A flaky endpoint never halts the sequence: the failure is logged (host only —
 * the URL may itself be a credential) and the step reports done.
 */
final class WebhookStepHandler implements StepHandler
{
    public function __construct(
        private readonly OutboundUrlGuard $guard,
    ) {}

    public function handle(AutomationRun $run, AutomationStep $step, array $context): StepOutcome
    {
        $endpoint = $step->webhookEndpoint();

        if ($endpoint === null || ! $endpoint->isHttps()) {
            return StepOutcome::done('webhook skipped (https required)');
        }

        try {
            $options = $this->guard->pinnedOptions($endpoint->url);
        } catch (UnsafeOutboundUrlException) {
            Log::warning('automation.webhook_blocked', ['host' => $endpoint->host(), 'run' => $run->ulid]);

            return StepOutcome::done('webhook_blocked (destination not allowed)');
        }

        $body = (string) json_encode([
            'trigger' => $context['trigger'] ?? null,
            'run' => $run->ulid,
            'context' => $this->publicContext($context),
        ]);

        try {
            $request = Http::withOptions($options)
                ->connectTimeout(5)
                ->timeout(10)
                ->withBody($body, 'application/json');

            if ($endpoint->secret !== null) {
                $request = $request->withHeader('X-Escenia-Signature', hash_hmac('sha256', $body, $endpoint->secret));
            }

            $response = $request->post($endpoint->url);

            return StepOutcome::done($response->successful()
                ? 'webhook_sent '.$response->status()
                : 'webhook_failed '.$response->status());
        } catch (Throwable $e) {
            // The exception message can carry the full URL: log its class only.
            Log::warning('automation.webhook_failed', ['host' => $endpoint->host(), 'error' => $e::class]);

            return StepOutcome::done('webhook_failed');
        }
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
