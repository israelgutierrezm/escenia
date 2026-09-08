<?php

declare(strict_types=1);

use App\Domain\Analytics\Enums\AnalyticsEventName;

it('classifies attendance and engagement events', function () {
    expect(AnalyticsEventName::AttendanceJoined->isAttendance())->toBeTrue()
        ->and(AnalyticsEventName::AttendanceHeartbeat->isAttendance())->toBeTrue()
        ->and(AnalyticsEventName::EngagementChat->isAttendance())->toBeFalse()
        ->and(AnalyticsEventName::EngagementChat->isEngagement())->toBeTrue()
        ->and(AnalyticsEventName::RegistrationCompleted->isEngagement())->toBeFalse();
});

it('carries a schema version for every event', function () {
    foreach (AnalyticsEventName::cases() as $name) {
        expect($name->version())->toBeGreaterThanOrEqual(1);
    }
});
