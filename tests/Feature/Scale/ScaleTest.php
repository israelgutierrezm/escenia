<?php

declare(strict_types=1);

use App\Application\Analytics\ExtractAnalyticsAction;
use App\Application\Outbox\DispatchOutboxAction;
use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Contracts\AnalyticsExporter;
use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\Outbox\Contracts\EventStreamPublisher;
use App\Infrastructure\Analytics\DatabaseAnalyticsCollector;
use App\Infrastructure\Analytics\FakeAnalyticsExporter;
use App\Infrastructure\Analytics\RecordAnalyticsEventJob;
use Illuminate\Support\Facades\Queue;

function unexportedAnalyticsCount(): int
{
    return AnalyticsEvent::query()->withoutGlobalScopes()->whereNull('exported_at')->count();
}

// ---- Analytics extraction to the warehouse -----------------------------------

it('extracts unexported analytics rows to the warehouse sink and marks them', function () {
    [, , $event] = makeWebinarHost();
    registerAttendee($event->ulid, 'Ann', 'ann@example.com');
    registerAttendee($event->ulid, 'Bob', 'bob@example.com');

    expect(unexportedAnalyticsCount())->toBeGreaterThanOrEqual(2);

    $exported = app(ExtractAnalyticsAction::class)->execute();

    /** @var FakeAnalyticsExporter $sink */
    $sink = app(AnalyticsExporter::class);
    expect($sink->exported)->toHaveCount($exported);
    expect($exported)->toBeGreaterThanOrEqual(2);
    expect(unexportedAnalyticsCount())->toBe(0);

    // Idempotent high-water mark: nothing left to export on a second run.
    expect(app(ExtractAnalyticsAction::class)->execute())->toBe(0);
});

it('exports the shipped rows in a warehouse-friendly shape', function () {
    [, , $event] = makeWebinarHost();
    registerAttendee($event->ulid, 'Ann', 'ann@example.com');

    app(ExtractAnalyticsAction::class)->execute();

    /** @var FakeAnalyticsExporter $sink */
    $sink = app(AnalyticsExporter::class);
    $row = $sink->exported[0];

    expect($row)->toHaveKeys(['ulid', 'tenant_id', 'event_id', 'name', 'version', 'occurred_at']);
    expect($row['name'])->toBeString();
});

// ---- Async analytics capture (queue mode) ------------------------------------

it('queues the analytics write when capture is set to queue', function () {
    [, , $event] = makeWebinarHost();

    config(['analytics.capture' => 'queue']);
    app()->forgetInstance(AnalyticsCollector::class);
    Queue::fake();

    registerAttendee($event->ulid, 'Ann', 'ann@example.com');

    Queue::assertPushed(RecordAnalyticsEventJob::class);
});

it('writes the analytics row when the record job runs', function () {
    [, , $event] = makeWebinarHost();
    $before = AnalyticsEvent::query()->withoutGlobalScopes()->count();

    (new RecordAnalyticsEventJob(
        eventId: $event->getKey(),
        attendeeId: null,
        name: 'registration.completed',
        properties: ['source' => 'test'],
        occurredAt: now()->toIso8601String(),
    ))->handle(app(DatabaseAnalyticsCollector::class));

    expect(AnalyticsEvent::query()->withoutGlobalScopes()->count())->toBe($before + 1);
});

// ---- Outbox event-stream fan-out ---------------------------------------------

it('fans out processed outbox events to the external stream', function () {
    $spy = new class implements EventStreamPublisher
    {
        /** @var list<array{topic: string, key: string}> */
        public array $published = [];

        public function publish(string $topic, string $key, array $payload): void
        {
            $this->published[] = ['topic' => $topic, 'key' => $key];
        }
    };
    app()->instance(EventStreamPublisher::class, $spy);

    [, , $event] = makeWebinarHost();
    registerAttendee($event->ulid, 'Ann', 'ann@example.com');

    app(DispatchOutboxAction::class)->execute();

    expect($spy->published)->not->toBeEmpty();
    expect(collect($spy->published)->pluck('topic'))->toContain('registration.completed');
    // The event ulid is used as the partition key (per-aggregate ordering).
    expect($spy->published[0]['key'])->not->toBe('');
});
