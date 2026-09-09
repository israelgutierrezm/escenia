<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Attendee;

use App\Application\Sponsorship\Actions\VisitBoothAction;
use App\Domain\Registration\Context\AttendeeContext;
use App\Domain\Sponsorship\Models\Booth;
use App\Domain\Sponsorship\Models\Sponsor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\VisitBoothRequest;
use App\Http\Resources\BoothResource;
use App\Http\Resources\SponsorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Attendee side of the expo: browse sponsors/booths and visit a booth (becoming
 * a lead + earning points).
 */
class ExpoController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function sponsors(): AnonymousResourceCollection
    {
        $attendee = $this->context->attendeeOrFail();

        return SponsorResource::collection(
            Sponsor::query()->where('event_id', $attendee->event_id)->orderBy('position')->get()
        );
    }

    public function booths(): AnonymousResourceCollection
    {
        $attendee = $this->context->attendeeOrFail();

        return BoothResource::collection(
            Booth::query()->where('event_id', $attendee->event_id)->with('sponsor')->orderBy('position')->get()
        );
    }

    public function visit(VisitBoothRequest $request, VisitBoothAction $action, string $booth): JsonResponse
    {
        $attendee = $this->context->attendeeOrFail();

        $model = Booth::query()
            ->where('ulid', $booth)
            ->where('event_id', $attendee->event_id)
            ->with('sponsor')
            ->firstOrFail();

        $note = $request->validated('note');
        $action->execute($attendee, $model, $note !== null ? (string) $note : null);

        return BoothResource::make($model->refresh()->load('sponsor'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
