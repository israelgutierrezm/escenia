<?php

declare(strict_types=1);

namespace App\Application\Engagement\Actions;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Engagement\Models\Resource;
use App\Domain\Engagement\Models\ResourceDownload;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Support\Facades\DB;

/**
 * Records that an attendee downloaded a resource. Idempotent per
 * (resource, attendee): the denormalized counter (and analytics) only moves on
 * the first download so repeated clicks don't inflate it.
 */
final class TrackResourceDownloadAction
{
    public function __construct(
        private readonly AnalyticsCollector $analytics,
    ) {}

    public function execute(Attendee $attendee, Resource $resource): Resource
    {
        return DB::transaction(function () use ($attendee, $resource): Resource {
            $download = ResourceDownload::query()->firstOrCreate([
                'resource_id' => $resource->getKey(),
                'attendee_id' => $attendee->getKey(),
            ]);

            if ($download->wasRecentlyCreated) {
                $resource->increment('downloads_count');

                $this->analytics->record(
                    AnalyticsEventName::EngagementResourceDownloaded,
                    $attendee->event()->firstOrFail(),
                    $attendee,
                    ['resource' => $resource->ulid],
                );
            }

            return $resource->refresh();
        });
    }
}
