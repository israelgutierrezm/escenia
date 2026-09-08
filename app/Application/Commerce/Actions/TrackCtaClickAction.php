<?php

declare(strict_types=1);

namespace App\Application\Commerce\Actions;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Commerce\Models\Cta;
use App\Domain\Commerce\Models\CtaClick;
use App\Domain\Registration\Models\Attendee;
use Illuminate\Support\Facades\DB;

/**
 * Records an attendee clicking a CTA: a click row, the denormalized counter, and
 * an analytics event feeding the CTA click-through report. Clicks may repeat.
 */
final class TrackCtaClickAction
{
    public function __construct(
        private readonly AnalyticsCollector $analytics,
    ) {}

    public function execute(Attendee $attendee, Cta $cta): Cta
    {
        return DB::transaction(function () use ($attendee, $cta): Cta {
            CtaClick::query()->create([
                'cta_id' => $cta->getKey(),
                'attendee_id' => $attendee->getKey(),
            ]);

            $cta->increment('clicks_count');

            $this->analytics->record(
                AnalyticsEventName::CommerceCtaClicked,
                $cta->event()->firstOrFail(),
                $attendee,
                ['cta' => $cta->ulid],
            );

            return $cta->refresh();
        });
    }
}
