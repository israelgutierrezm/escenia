<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

/**
 * Capabilities compose an event's behaviour (see master-specification.md and
 * ADR-016). An event enables the capabilities it needs; each capability may be
 * gated by the tenant's plan entitlements before it can be turned on.
 */
enum Capability: string
{
    case Registration = 'registration';
    case Payments = 'payments';
    case Chat = 'chat';
    case Qa = 'qa';
    case Polls = 'polls';
    case Tests = 'tests';
    case Certificates = 'certificates';
    case Networking = 'networking';
    case Expo = 'expo';
    case Sponsors = 'sponsors';
    case Recording = 'recording';
    case Multistream = 'multistream';
    case Automation = 'automation';
    case Ai = 'ai';
    case Commerce = 'commerce';
    case Replay = 'replay';
    case Translation = 'translation';
    case Captions = 'captions';
    case WhiteLabel = 'white_label';
    case Gamification = 'gamification';
    case BreakoutRooms = 'breakout_rooms';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $capability): string => $capability->value, self::cases());
    }
}
