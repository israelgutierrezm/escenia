<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Attendee;

use App\Application\Engagement\Actions\TrackResourceDownloadAction;
use App\Domain\Engagement\Models\Resource;
use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\ResourceResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Attendee side of resources: list handouts and record a download (idempotent
 * per attendee).
 */
class ResourceController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $attendee = $this->context->attendeeOrFail();

        $resources = Resource::query()
            ->where('event_id', $attendee->event_id)
            ->orderBy('position')
            ->get();

        return ResourceResource::collection($resources);
    }

    public function download(TrackResourceDownloadAction $action, string $resource): ResourceResource
    {
        $attendee = $this->context->attendeeOrFail();

        $model = Resource::query()
            ->where('ulid', $resource)
            ->where('event_id', $attendee->event_id)
            ->firstOrFail();

        return ResourceResource::make($action->execute($attendee, $model));
    }
}
