<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\Notifier;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Registration\Models\Contact;
use Illuminate\Support\Facades\Log;

/**
 * Default notifier: records the notification (channel `log`) and writes a
 * structured log line. No external delivery — real email/SMS is a future channel
 * behind {@see Notifier}. The tenant is filled from the current context.
 */
final class LogNotifier implements Notifier
{
    public function send(string $to, string $subject, string $body, ?Contact $contact = null): Notification
    {
        $notification = Notification::query()->create([
            'contact_id' => $contact?->getKey(),
            'channel' => 'log',
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'sent_at' => now(),
        ]);

        Log::info('notification.sent', [
            'notification' => $notification->ulid,
            'channel' => 'log',
            'to' => $to,
        ]);

        return $notification;
    }
}
