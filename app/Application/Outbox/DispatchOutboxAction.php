<?php

declare(strict_types=1);

namespace App\Application\Outbox;

use App\Application\Commerce\Handlers\FulfillPaidOrderHandler;
use App\Domain\Outbox\Contracts\OutboxHandler;
use App\Domain\Outbox\Models\OutboxEvent;
use App\Domain\Tenancy\Context\TenantContext;
use Throwable;

/**
 * Publishes pending outbox events (ADR-007). Reads unscoped across tenants (it
 * is a system process), routes each event to its handler within the event's
 * tenant context, and marks it processed. A failing handler leaves the event
 * unprocessed (attempts incremented) for the next run — at-least-once delivery.
 */
final class DispatchOutboxAction
{
    /**
     * Topic → handler class.
     *
     * @var array<string, class-string<OutboxHandler>>
     */
    private const HANDLERS = [
        'order.paid' => FulfillPaidOrderHandler::class,
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
        $handlerClass = self::HANDLERS[$event->topic] ?? null;

        if ($handlerClass === null) {
            return;
        }

        $handler = app($handlerClass);
        $tenant = $event->tenant;

        if ($tenant === null) {
            $handler->handle($event);

            return;
        }

        $this->tenantContext->runFor($tenant, fn () => $handler->handle($event));
    }
}
