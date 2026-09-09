<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Programmatic;

use App\Domain\Enterprise\Models\ApiKey;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sample programmatic (Developer Platform) endpoint, authenticated by an API
 * key rather than a user session. Demonstrates scope enforcement: listing
 * events requires the `events.read` scope. The tenant context is already bound
 * by the AuthenticateApiKey middleware, so the query is tenant-scoped.
 */
class EventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requireScope($request, 'events.read');

        return EventResource::collection(
            Event::query()->latest('id')->get()
        );
    }

    private function requireScope(Request $request, string $scope): void
    {
        $apiKey = $request->attributes->get('api_key');

        abort_unless(
            $apiKey instanceof ApiKey && $apiKey->hasScope($scope),
            Response::HTTP_FORBIDDEN,
            "This API key is missing the required scope: {$scope}.",
        );
    }
}
