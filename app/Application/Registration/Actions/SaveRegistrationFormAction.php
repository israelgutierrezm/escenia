<?php

declare(strict_types=1);

namespace App\Application\Registration\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Registration\Models\RegistrationForm;

/**
 * Creates or updates the single registration form for an event (host side).
 * `fields` are the ordered custom field definitions beyond name + email.
 */
final class SaveRegistrationFormAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    public function execute(Event $event, User $actor, bool $isOpen, array $fields): RegistrationForm
    {
        $form = RegistrationForm::query()->updateOrCreate(
            ['event_id' => $event->getKey()],
            ['is_open' => $isOpen, 'fields' => $fields],
        );

        $this->audit->log('registration.form.saved', actor: $actor, tenant: $event->tenant, auditable: $form, context: [
            'is_open' => $isOpen,
        ]);

        return $form;
    }
}
