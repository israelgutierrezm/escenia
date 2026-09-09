<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Domain\Sponsorship\Models\Booth;
use App\Domain\Sponsorship\Models\BoothLead;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\BoothLeadResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEnterprise', $model);

        $boothIds = Booth::query()->where('event_id', $model->getKey())->pluck('id');

        $leads = BoothLead::query()
            ->whereIn('booth_id', $boothIds)
            ->with(['attendee', 'booth'])
            ->latest('id')
            ->paginate(50);

        return BoothLeadResource::collection($leads);
    }
}
