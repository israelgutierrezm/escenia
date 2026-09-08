<?php

declare(strict_types=1);

namespace App\Application\Automation\Steps;

use App\Domain\Automation\Contracts\StepHandler;
use App\Domain\Automation\Models\AutomationRun;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\ValueObjects\StepOutcome;
use App\Domain\Notifications\Contracts\Notifier;
use App\Domain\Registration\Models\Contact;

/**
 * Sends a notification to the trigger's contact via the Notifier (the reminders
 * primitive). Delivery goes through the configured channel (log by default).
 */
final class NotifyStepHandler implements StepHandler
{
    public function __construct(
        private readonly Notifier $notifier,
    ) {}

    public function handle(AutomationRun $run, AutomationStep $step, array $context): StepOutcome
    {
        $to = (string) ($context['contact.email'] ?? '');

        if ($to === '') {
            return StepOutcome::done('notify skipped (no recipient)');
        }

        $contact = Contact::query()->find((int) ($context['_contact_id'] ?? 0));

        $this->notifier->send(
            $to,
            (string) ($step->config['subject'] ?? 'Notification'),
            (string) ($step->config['body'] ?? ''),
            $contact,
        );

        return StepOutcome::done("notified: {$to}");
    }
}
