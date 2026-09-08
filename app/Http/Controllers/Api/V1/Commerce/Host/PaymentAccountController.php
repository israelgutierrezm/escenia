<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Commerce\Host;

use App\Application\Commerce\Actions\SavePaymentAccountAction;
use App\Application\Commerce\DTOs\SavePaymentAccountData;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Commerce\Models\PaymentAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commerce\SavePaymentAccountRequest;
use App\Http\Resources\PaymentAccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Tenant-level management of connected payment gateways. Credentials are write-
 * only (encrypted, never serialized back).
 */
class PaymentAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizeCommerce($request);

        return PaymentAccountResource::collection(PaymentAccount::query()->get());
    }

    public function save(SavePaymentAccountRequest $request, SavePaymentAccountAction $action): JsonResponse
    {
        $this->authorizeCommerce($request);

        $account = $action->execute($request->user(), SavePaymentAccountData::fromArray($request->validated()));

        // PUT upsert: always 200.
        return PaymentAccountResource::make($account)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_OK);
    }

    private function authorizeCommerce(Request $request): void
    {
        abort_unless(
            $request->user()?->can(Permission::CommerceManage->value) === true,
            JsonResponse::HTTP_FORBIDDEN,
        );
    }
}
