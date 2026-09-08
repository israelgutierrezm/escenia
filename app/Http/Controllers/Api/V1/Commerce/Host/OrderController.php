<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce\Host;

use App\Domain\Commerce\Models\Order;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewCommerce', $model);

        $orders = Order::query()
            ->where('event_id', $model->getKey())
            ->with('items')
            ->latest('id')
            ->paginate(50);

        return OrderResource::collection($orders);
    }
}
