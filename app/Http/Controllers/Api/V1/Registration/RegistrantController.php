<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Registration;

use App\Domain\Registration\Models\Registration;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Host view of who has registered for an event.
 */
class RegistrantController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEngagement', $model);

        $registrations = Registration::query()
            ->where('event_id', $model->getKey())
            ->with('contact')
            ->latest('id')
            ->paginate(50);

        return RegistrationResource::collection($registrations);
    }
}
