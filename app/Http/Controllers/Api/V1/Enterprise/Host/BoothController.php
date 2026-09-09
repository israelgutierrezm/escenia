<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Application\Sponsorship\Actions\CreateBoothAction;
use App\Domain\Sponsorship\Models\Booth;
use App\Domain\Sponsorship\Models\Sponsor;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\CreateBoothRequest;
use App\Http\Resources\BoothResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BoothController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEnterprise', $model);

        return BoothResource::collection(
            Booth::query()->where('event_id', $model->getKey())->with('sponsor')->orderBy('position')->get()
        );
    }

    public function store(CreateBoothRequest $request, CreateBoothAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageEnterprise', $model);

        $sponsor = Sponsor::query()
            ->where('ulid', (string) $request->validated('sponsor'))
            ->where('event_id', $model->getKey())
            ->firstOrFail();

        $description = $request->validated('description');
        $url = $request->validated('url');

        $booth = $action->execute(
            $model,
            $request->user(),
            $sponsor,
            (string) $request->validated('name'),
            $description !== null ? (string) $description : null,
            $url !== null ? (string) $url : null,
        );

        return BoothResource::make($booth->load('sponsor'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
