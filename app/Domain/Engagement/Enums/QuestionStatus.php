<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Enums;

enum QuestionStatus: string
{
    case Open = 'open';
    case Answered = 'answered';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
