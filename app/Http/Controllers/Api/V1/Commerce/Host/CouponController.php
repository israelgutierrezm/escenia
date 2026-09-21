<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce\Host;

use App\Application\Commerce\Actions\CreateCouponAction;
use App\Application\Commerce\DTOs\CouponData;
use App\Domain\Commerce\Models\Coupon;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\CouponRequest;
use App\Http\Resources\CouponResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Host management of discount coupons for an event: list, create, and
 * deactivate (soft — the coupon is kept so paid orders retain their history).
 */
class CouponController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewCommerce', $model);

        return CouponResource::collection(
            Coupon::query()->where('event_id', $model->getKey())->latest('id')->get()
        );
    }

    public function store(CouponRequest $request, CreateCouponAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageCommerce', $model);

        $coupon = $action->execute($model, CouponData::fromArray($request->validated()));

        return CouponResource::make($coupon)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function deactivate(string $event, string $coupon): CouponResource
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageCommerce', $model);

        $couponModel = Coupon::query()
            ->where('event_id', $model->getKey())
            ->where('ulid', $coupon)
            ->firstOrFail();

        $couponModel->forceFill(['is_active' => false])->save();

        return CouponResource::make($couponModel);
    }
}
