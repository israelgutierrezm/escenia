<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Enums;

enum DestinationProtocol: string
{
    case Rtmp = 'rtmp';
    case Rtmps = 'rtmps';
    case Srt = 'srt';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $protocol): string => $protocol->value, self::cases());
    }
}
