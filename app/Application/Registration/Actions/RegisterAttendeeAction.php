<?php

declare(strict_types=1);

namespace App\Application\Registration\Actions;

use App\Application\Registration\DTOs\RegisterAttendeeData;
use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Events\Models\Event;
use App\Domain\Outbox\Models\OutboxEvent;
use App\Domain\Registration\Exceptions\RegistrationClosedException;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Registration\Models\Registration;
use App\Domain\Registration\Models\RegistrationForm;
use App\Domain\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Public flow: a person registers for an event. There is no tenant session — the
 * event is looked up unscoped and the tenant context is derived from it, never
 * trusted from the request.
 *
 * The call is idempotent per (event, email): the contact and registration are
 * reused, and the attendee's join token is rotated so the caller always gets a
 * working credential back. The raw token is returned once; only its hash is
 * stored.
 *
 * @phpstan-type RegisterResult array{attendee: Attendee, registration: Registration, token: string}
 */
final class RegisterAttendeeAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AnalyticsCollector $analytics,
        private readonly IssueAttendeeAction $issueAttendee,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{attendee: Attendee, registration: Registration, token: string}
     */
    public function execute(string $eventUlid, RegisterAttendeeData $data): array
    {
        $event = Event::query()
            ->withoutGlobalScopes()
            ->where('ulid', $eventUlid)
            ->first();

        if ($event === null) {
            throw new RegistrationClosedException;
        }

        return $this->tenantContext->runFor($event->tenant, function () use ($event, $data): array {
            $form = RegistrationForm::query()->where('event_id', $event->getKey())->first();

            if ($form === null || ! $form->is_open) {
                throw new RegistrationClosedException;
            }

            return DB::transaction(function () use ($event, $data): array {
                $issued = $this->issueAttendee->execute($event, $data->name, $data->email);
                $attendee = $issued['attendee'];

                $registration = Registration::query()->updateOrCreate(
                    ['event_id' => $event->getKey(), 'contact_id' => $issued['contact']->getKey()],
                    ['answers' => $data->answers],
                );

                $attendee->forceFill(['registration_id' => $registration->getKey()])->save();

                $this->audit->log('registration.registered', tenant: $event->tenant, auditable: $attendee, context: [
                    'event' => $event->ulid,
                ]);

                $this->analytics->record(AnalyticsEventName::RegistrationCompleted, $event, $attendee, [
                    'returning' => ! $attendee->wasRecentlyCreated,
                    'attribution' => $data->attribution,
                ]);

                // Publish the trigger to the outbox (ADR-007) for automations.
                OutboxEvent::query()->create([
                    'topic' => 'registration.completed',
                    'payload' => [
                        'event_id' => $event->getKey(),
                        'contact_id' => $issued['contact']->getKey(),
                        'attendee_id' => $attendee->getKey(),
                    ],
                    'available_at' => now(),
                ]);

                return [
                    'attendee' => $attendee,
                    'registration' => $registration,
                    'token' => $issued['token'],
                ];
            });
        });
    }
}
