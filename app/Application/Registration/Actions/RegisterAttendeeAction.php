<?php

declare(strict_types=1);

namespace App\Application\Registration\Actions;

use App\Application\Registration\DTOs\RegisterAttendeeData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Exceptions\RegistrationClosedException;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Registration\Models\Contact;
use App\Domain\Registration\Models\Registration;
use App\Domain\Registration\Models\RegistrationForm;
use App\Domain\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            $rawToken = Str::random(48);

            return DB::transaction(function () use ($event, $data, $rawToken): array {
                $email = Str::lower(trim($data->email));

                $contact = Contact::query()->firstOrCreate(
                    ['workspace_id' => $event->workspace_id, 'email' => $email],
                    ['name' => $data->name],
                );

                $registration = Registration::query()->updateOrCreate(
                    ['event_id' => $event->getKey(), 'contact_id' => $contact->getKey()],
                    ['answers' => $data->answers],
                );

                $attendee = Attendee::query()->updateOrCreate(
                    ['event_id' => $event->getKey(), 'contact_id' => $contact->getKey()],
                    [
                        'registration_id' => $registration->getKey(),
                        'name' => $data->name,
                        'email' => $email,
                        'join_token_hash' => hash('sha256', $rawToken),
                    ],
                );

                $this->audit->log('registration.registered', tenant: $event->tenant, auditable: $attendee, context: [
                    'event' => $event->ulid,
                ]);

                return [
                    'attendee' => $attendee,
                    'registration' => $registration,
                    'token' => $rawToken,
                ];
            });
        });
    }
}
