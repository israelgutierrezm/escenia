<?php

declare(strict_types=1);

namespace App\Application\Automation\Steps;

use App\Domain\Automation\Contracts\StepHandler;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\ValueObjects\StepOutcome;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Outbound webhook: POSTs the (public) trigger context to a tenant-configured
 * URL, HMAC-signed with the step secret so the receiver can verify authenticity.
 * Only https is allowed. A flaky endpoint never halts the sequence — the failure
 * is logged and the step reports done.
 */
final class WebhookStepHandler implements StepHandler
{
    public function handle(AutomationRun $run, AutomationStep $step, array $context): StepOutcome
    {
        $url = (string) ($step->config['url'] ?? '');

        if (! str_starts_with($url, 'https://')) {
            return StepOutcome::done('webhook skipped (https required)');
        }

        $body = (string) json_encode([
            'trigger' => $context['trigger'] ?? null,
            'run' => $run->ulid,
            'context' => $this->publicContext($context),
        ]);

        $secret = (string) ($step->config['secret'] ?? '');

        try {
            $request = Http::timeout(10)->withBody($body, 'application/json');

            if ($secret !== '') {
                $request = $request->withHeader('X-Escenia-Signature', hash_hmac('sha256', $body, $secret));
            }

            $response = $request->post($url);

            return StepOutcome::done($response->successful()
                ? 'webhook_sent '.$response->status()
                : 'webhook_failed '.$response->status());
        } catch (Throwable $e) {
            Log::warning('automation.webhook_failed', ['url' => $url, 'error' => $e->getMessage()]);

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
