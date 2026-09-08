<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Models\Notification;
use App\Domain\Registration\Models\Contact;

/**
 * Sends a notification to a recipient. The default implementation records to the
 * `log` channel; this contract is the seam for real delivery (email/SMS) without
 * changing callers. Enables the reminders deferred from Fase 5.
 */
interface Notifier
{
    public function send(string $to, string $subject, string $body, ?Contact $contact = null): Notification;
}
