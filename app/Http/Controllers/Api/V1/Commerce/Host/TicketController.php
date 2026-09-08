<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce\Host;

use App\Application\Commerce\Actions\CreateTicketAction;
use App\Application\Commerce\DTOs\CreateTicketData;
use App\Domain\Commerce\Models\Ticket;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\CreateTicketRequest;
use App\Http\Resources\TicketResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewCommerce', $model);

        $tickets = Ticket::query()
            ->where('event_id', $model->getKey())
            ->orderBy('position')
            ->get();

        return TicketResource::collection($tickets);
    }

    public function store(CreateTicketRequest $request, CreateTicketAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageCommerce', $model);

        $ticket = $action->execute($model, $request->user(), CreateTicketData::fromArray($request->validated()));

        return TicketResource::make($ticket)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
