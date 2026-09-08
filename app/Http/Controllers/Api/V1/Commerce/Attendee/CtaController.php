<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce\Attendee;

use App\Application\Commerce\Actions\TrackCtaClickAction;
use App\Domain\Commerce\Models\Cta;
use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\CtaResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Attendee side of CTAs: see the live CTAs for their event and register a click.
 */
class CtaController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $attendee = $this->context->attendeeOrFail();

        $ctas = Cta::query()
            ->where('event_id', $attendee->event_id)
            ->where('is_active', true)
            ->with('ticket')
            ->orderBy('position')
            ->get()
            ->filter(fn (Cta $cta): bool => $cta->isLive())
            ->values();

        return CtaResource::collection($ctas);
    }

    public function click(TrackCtaClickAction $action, string $cta): CtaResource
    {
        $attendee = $this->context->attendeeOrFail();

        $model = Cta::query()
            ->where('ulid', $cta)
            ->where('event_id', $attendee->event_id)
            ->firstOrFail();

        return CtaResource::make($action->execute($attendee, $model));
    }
}
