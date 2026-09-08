<?php

declare(strict_types=1);

namespace App\Application\Registration\Actions;

use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Registration\Models\Contact;
use Illuminate\Support\Str;

/**
 * Issues (or re-issues) an attendee for an event: finds-or-creates the workspace
 * Contact, upserts the Attendee, and rotates the join token — returning the raw
 * token once. Idempotent per (event, email). Assumes the caller already runs
 * inside the event's tenant context and its own transaction.
 *
 * Shared by public registration and by checkout so the audience-identity rules
 * live in one place.
 *
 * @phpstan-type IssueResult array{attendee: Attendee, contact: Contact, token: string}
 */
final class IssueAttendeeAction
{
    /**
     * @return array{attendee: Attendee, contact: Contact, token: string}
     */
    public function execute(Event $event, string $name, string $email): array
    {
        $rawToken = Str::random(48);
        $email = Str::lower(trim($email));

        $contact = Contact::query()->firstOrCreate(
            ['workspace_id' => $event->workspace_id, 'email' => $email],
            ['name' => $name],
        );

        $attendee = Attendee::query()->updateOrCreate(
            ['event_id' => $event->getKey(), 'contact_id' => $contact->getKey()],
            [
                'name' => $name,
                'email' => $email,
                'join_token_hash' => hash('sha256', $rawToken),
            ],
        );

        return ['attendee' => $attendee, 'contact' => $contact, 'token' => $rawToken];
    }
}
