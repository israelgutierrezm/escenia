<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

/**
 * The kind of event. Types are labels/presets that drive default capabilities
 * (via templates) — they must NEVER become separate table trees. Behaviour is
 * composed from capabilities, not branched on the type (see ADR-016).
 */
enum EventType: string
{
    case Webinar = 'webinar';
    case LiveStudio = 'live_studio';
    case Evergreen = 'evergreen';
    case Simulive = 'simulive';
    case Training = 'training';
    case Course = 'course';
    case TownHall = 'town_hall';
    case ProductLaunch = 'product_launch';
    case VirtualConference = 'virtual_conference';
    case HybridEvent = 'hybrid_event';
    case Podcast = 'podcast';
    case OnDemand = 'on_demand';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
