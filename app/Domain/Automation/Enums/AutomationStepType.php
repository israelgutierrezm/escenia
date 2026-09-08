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
}
