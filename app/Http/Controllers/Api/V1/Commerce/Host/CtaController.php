<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce\Host;

use App\Application\Commerce\Actions\CreateCtaAction;
use App\Application\Commerce\DTOs\CreateCtaData;
use App\Domain\Commerce\Models\Cta;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\CreateCtaRequest;
use App\Http\Resources\CtaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CtaController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewCommerce', $model);

        $ctas = Cta::query()
            ->where('event_id', $model->getKey())
            ->with('ticket')
            ->orderBy('position')
            ->get();

        return CtaResource::collection($ctas);
    }

    public function store(CreateCtaRequest $request, CreateCtaAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageCommerce', $model);

        $cta = $action->execute($model, $request->user(), CreateCtaData::fromArray($request->validated()));

        return CtaResource::make($cta->load('ticket'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
