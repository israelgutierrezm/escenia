<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce;

use App\Application\Commerce\Actions\StartCheckoutAction;
use App\Application\Commerce\DTOs\CheckoutData;
use App\Domain\Commerce\Models\Ticket;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\CheckoutRequest;
use App\Http\Resources\AttendeeResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public checkout. The event is resolved unscoped and the tenant derived from it
 * (never from the request). Starting a checkout issues the buyer's attendee and
 * returns the join token once, plus the gateway intent to complete payment.
 */
class PublicCheckoutController extends Controller
{
    public function tickets(string $event): AnonymousResourceCollection
    {
        $model = Event::query()->withoutGlobalScopes()->where('ulid', $event)->firstOrFail();

        $tickets = Ticket::query()
            ->withoutGlobalScopes()
            ->where('event_id', $model->getKey())
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        return TicketResource::collection($tickets);
    }

    public function store(CheckoutRequest $request, StartCheckoutAction $action, string $event): JsonResponse
    {
        $result = $action->execute($event, CheckoutData::fromArray($request->validated()));
        $intent = $result['intent'];

        return response()->json([
            'data' => [
                'order' => OrderResource::make($result['order']),
                'attendee' => AttendeeResource::make($result['attendee']),
                'token' => $result['token'],
                'payment' => [
                    'reference' => $intent->reference,
                    'status' => $intent->status->value,
                    'client_secret' => $intent->clientSecret,
                    'redirect_url' => $intent->redirectUrl,
                ],
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
