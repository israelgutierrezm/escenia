<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Enums;

/**
 * The catalog of analytics events. Each event carries a schema `version` so the
 * shape of its `properties` payload can evolve without breaking historical rows
 * or the eventual ClickHouse pipeline (ADR-005). Bump the version when the
 * property shape of an event changes.
 */
enum AnalyticsEventName: string
{
    case RegistrationCompleted = 'registration.completed';
    case AttendanceJoined = 'attendance.joined';
    case AttendanceHeartbeat = 'attendance.heartbeat';
    case AttendanceLeft = 'attendance.left';
    case EngagementChat = 'engagement.chat';
    case EngagementQuestionAsked = 'engagement.question_asked';
    case EngagementQuestionVoted = 'engagement.question_voted';
    case EngagementPollVoted = 'engagement.poll_voted';
    case EngagementResourceDownloaded = 'engagement.resource_downloaded';

    /**
     * Current schema version of this event's `properties` payload.
     */
    public function version(): int
    {
        return 1;
    }

    public function isAttendance(): bool
    {
        return in_array($this, [self::AttendanceJoined, self::AttendanceHeartbeat, self::AttendanceLeft], true);
    }

    public function isEngagement(): bool
    {
        return str_starts_with($this->value, 'engagement.');
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $name): string => $name->value, self::cases());
    }
}
