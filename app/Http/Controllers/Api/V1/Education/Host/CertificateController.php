<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Education\Host;

use App\Application\Education\Actions\IssueCertificatesAction;
use App\Domain\Education\Models\Certificate;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CertificateController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEducation', $model);

        $certificates = Certificate::query()
            ->where('event_id', $model->getKey())
            ->latest('id')
            ->paginate(50);

        return CertificateResource::collection($certificates);
    }

    public function issue(Request $request, IssueCertificatesAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageEducation', $model);

        $count = $action->execute($model, $request->user());

        return response()->json(['data' => ['issued' => $count]]);
    }
}
