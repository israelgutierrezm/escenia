<?php

declare(strict_types=1);

namespace App\Domain\Studio\Enums;

/**
 * A participant's position in the studio flow (Adaptive Audience seed):
 * invited → green room → backstage → stage, with `left` as the terminal state.
 * Transitions are guarded like the event state machine (see ADR-019).
 */
enum ParticipantStage: string
{
    case Invited = 'invited';
    case GreenRoom = 'green_room';
    case Backstage = 'backstage';
    case Stage = 'stage';
    case Left = 'left';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Invited => [self::GreenRoom, self::Left],
            self::GreenRoom => [self::Backstage, self::Left],
            self::Backstage => [self::Stage, self::GreenRoom, self::Left],
            self::Stage => [self::Backstage, self::Left],
            self::Left => [],
        };
    }

    /**
     * Whether the participant currently holds a seat in the media room.
     */
    public function isInRoom(): bool
    {
        return match ($this) {
            self::GreenRoom, self::Backstage, self::Stage => true,
            self::Invited, self::Left => false,
        };
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $stage): string => $stage->value, self::cases());
    }
}
