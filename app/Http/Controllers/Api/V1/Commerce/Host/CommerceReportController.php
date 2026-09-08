<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce\Host;

use App\Application\Commerce\EventCommerceReport;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CommerceReportController extends Controller
{
    use ResolvesEvent;

    public function __construct(
        private readonly EventCommerceReport $report,
    ) {}

    public function revenue(string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewCommerce', $model);

        return response()->json(['data' => $this->report->revenue($model)]);
    }
}
