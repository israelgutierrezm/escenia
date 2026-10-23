<?php

declare(strict_types=1);

namespace App\Domain\Automation\Enums;

enum AutomationStepType: string
{
    case Webhook = 'webhook';
    case TagContact = 'tag_contact';
    case Notify = 'notify';
    case Wait = 'wait';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }

    /**
     * The `config` keys this step's handler reads; anything else is dropped when
     * the step is saved. A webhook keeps its URL and secret in the encrypted
     * `credentials` instead (ADR-037).
     *
     * @return list<string>
     */
    public function configKeys(): array
    {
        return match ($this) {
            self::Webhook => [],
            self::TagContact => ['tag'],
            self::Notify => ['subject', 'body'],
            self::Wait => ['seconds'],
        };
    }
}
