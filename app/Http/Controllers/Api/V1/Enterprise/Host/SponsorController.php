<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Application\Sponsorship\Actions\CreateSponsorAction;
use App\Domain\Sponsorship\Enums\SponsorTier;
use App\Domain\Sponsorship\Models\Sponsor;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\CreateSponsorRequest;
use App\Http\Resources\SponsorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SponsorController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEnterprise', $model);

        return SponsorResource::collection(
            Sponsor::query()->where('event_id', $model->getKey())->orderBy('position')->get()
        );
    }

    public function store(CreateSponsorRequest $request, CreateSponsorAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageEnterprise', $model);

        $logo = $request->validated('logo_url');
        $website = $request->validated('website_url');

        $sponsor = $action->execute(
            $model,
            $request->user(),
            (string) $request->validated('name'),
            SponsorTier::from((string) $request->validated('tier')),
            $logo !== null ? (string) $logo : null,
            $website !== null ? (string) $website : null,
        );

        return SponsorResource::make($sponsor)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
