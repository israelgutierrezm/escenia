<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise\Host;

use App\Application\Enterprise\Actions\IssueApiKeyAction;
use App\Application\Enterprise\Actions\RevokeApiKeyAction;
use App\Domain\AccessControl\Enums\Permission;
use App\Domain\Enterprise\Models\ApiKey;
use App\Http\Concerns\AuthorizesTenantPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\IssueApiKeyRequest;
use App\Http\Resources\ApiKeyResource;
use App\Http\Resources\IssuedApiKeyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Tenant-level management of programmatic API keys. The raw key is returned
 * exactly once, at creation. Managing keys is owner-level (`tenant.manage`).
 */
class ApiKeyController extends Controller
{
    use AuthorizesTenantPermission;

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorizePermission($request, Permission::TenantManage);

        return ApiKeyResource::collection(
            ApiKey::query()->latest('id')->get()
        );
    }

    public function store(IssueApiKeyRequest $request, IssueApiKeyAction $action): JsonResponse
    {
        $this->authorizePermission($request, Permission::TenantManage);

        /** @var list<string> $scopes */
        $scopes = array_values($request->validated('scopes'));
        $expiresAtInput = $request->validated('expires_at');
        $expiresAt = is_string($expiresAtInput) ? Carbon::parse($expiresAtInput) : null;

        $issued = $action->execute($request->user(), (string) $request->validated('name'), $scopes, $expiresAt);

        return IssuedApiKeyResource::make($issued)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function destroy(Request $request, RevokeApiKeyAction $action, string $apiKey): ApiKeyResource
    {
        $this->authorizePermission($request, Permission::TenantManage);

        $model = ApiKey::query()->where('ulid', $apiKey)->firstOrFail();

        return ApiKeyResource::make($action->execute($request->user(), $model));
    }
}
