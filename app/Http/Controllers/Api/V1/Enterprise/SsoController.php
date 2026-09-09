<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise;

use App\Application\Enterprise\Actions\CompleteSsoLoginAction;
use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\SsoCallbackRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public SSO entry points. `metadata` starts authentication (returns the IdP
 * authorization URL); `callback` completes it — provisioning the user via
 * {@see CompleteSsoLoginAction} and establishing the first-party Sanctum
 * session. The connection is resolved unscoped by its public id.
 */
class SsoController extends Controller
{
    public function metadata(Request $request, IdentityProvider $identityProvider, string $connection): JsonResponse
    {
        $model = $this->resolveActive($connection);

        $redirectUri = $request->query('redirect_uri');
        $redirectUri = is_string($redirectUri) && $redirectUri !== ''
            ? $redirectUri
            : url("/api/v1/sso/{$model->ulid}/callback");

        $state = (string) Str::uuid();

        return response()->json([
            'data' => [
                'connection' => $model->ulid,
                'provider' => $model->provider->value,
                'authorization_url' => $identityProvider->authorizationUrl($model, $redirectUri, $state),
                'state' => $state,
            ],
        ]);
    }

    public function callback(SsoCallbackRequest $request, CompleteSsoLoginAction $action, string $connection): JsonResponse
    {
        $model = $this->resolveActive($connection);

        $user = $action->execute($model, $request->validated());

        // Establish the first-party session (same contract as password login).
        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Login semantics: 200 even when the user was just provisioned (the
        // resource's wasRecentlyCreated flag would otherwise force a 201).
        return UserResource::make($user->load('tenants'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_OK);
    }

    private function resolveActive(string $connection): SsoConnection
    {
        $model = SsoConnection::query()
            ->withoutGlobalScopes()
            ->where('ulid', $connection)
            ->where('is_active', true)
            ->first();

        if ($model === null) {
            abort(Response::HTTP_NOT_FOUND, 'SSO connection not found.');
        }

        return $model;
    }
}
