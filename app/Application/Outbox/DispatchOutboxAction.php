<?php

declare(strict_types=1);

namespace App\Application\Outbox;

use App\Application\Ai\IndexTranscriptHandler;
use App\Application\Automation\HandleAutomationTrigger;
use App\Application\Commerce\Handlers\FulfillPaidOrderHandler;
use App\Application\Content\Handlers\RegisterBroadcastRecordingHandler;
use App\Application\Education\Handlers\IssueCertificateHandler;
use App\Domain\Outbox\Contracts\OutboxHandler;
use App\Domain\Outbox\Models\OutboxEvent;
use App\Domain\Tenancy\Context\TenantContext;
use Throwable;

/**
 * Publishes pending outbox events (ADR-007). Reads unscoped across tenants (it
 * is a system process), routes each event to its handler(s) within the event's
 * tenant context, and marks it processed. A failing handler leaves the event
 * unprocessed (attempts incremented) for the next run — at-least-once delivery.
 */
final class DispatchOutboxAction
{
    /**
     * Topic → ordered list of handler classes. One event can feed several
     * consumers (e.g. order.paid fulfils the order AND triggers automations).
     *
     * @var array<string, list<class-string<OutboxHandler>>>
     */
    private const HANDLERS = [
        'order.paid' => [FulfillPaidOrderHandler::class, HandleAutomationTrigger::class],
        'registration.completed' => [HandleAutomationTrigger::class],
        'event.ended' => [HandleAutomationTrigger::class],
        'broadcast.ended' => [RegisterBroadcastRecordingHandler::class],
        'transcript.ready' => [IndexTranscriptHandler::class],
        'assessment.submitted' => [IssueCertificateHandler::class],
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return int number of events processed
     */
    public function execute(int $limit = 100): int
    {
        $events = OutboxEvent::query()
            ->withoutGlobalScopes()
            ->whereNull('processed_at')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($events as $event) {
            try {
                $this->process($event);
                $event->forceFill(['processed_at' => now()])->save();
                $processed++;
            } catch (Throwable $e) {
                $event->increment('attempts');
                report($e);
            }
        }

        return $processed;
    }

    private function process(OutboxEvent $event): void
    {
        $handlers = self::HANDLERS[$event->topic] ?? [];

        if ($handlers === []) {
            return;
        }

        $run = function () use ($event, $handlers): void {
            foreach ($handlers as $handlerClass) {
                app($handlerClass)->handle($event);
            }
        };

        $tenant = $event->tenant;

        if ($tenant === null) {
            $run();

            return;
        }

        $this->tenantContext->runFor($tenant, $run);
    }
}
