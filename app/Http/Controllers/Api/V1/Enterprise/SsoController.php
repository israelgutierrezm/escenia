<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise;

use App\Application\Enterprise\Actions\CompleteSsoLoginAction;
use App\Application\Enterprise\Actions\StartSsoLoginAction;
use App\Application\Enterprise\SsoLoginAttempts;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enterprise\DiscoverSsoRequest;
use App\Http\Requests\Enterprise\SsoCallbackRequest;
use App\Http\Requests\Enterprise\StartSsoLoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public SSO entry points. `discover` finds the connections that may log in a
 * work email; `metadata` starts authentication — it returns the IdP
 * authorization URL and the `state`, and sets the HttpOnly binding cookie that
 * ties the login to this browser; `callback` completes an OIDC login —
 * provisioning the user via {@see CompleteSsoLoginAction} and establishing the
 * first-party Sanctum session (SAML completes at {@see SamlController::acs}).
 * Connections are resolved unscoped by their public id.
 */
class SsoController extends Controller
{
    public const BINDING_COOKIE = 'escenia_sso';

    public const COOKIE_PATH = '/api/v1/sso';

    public function discover(DiscoverSsoRequest $request): JsonResponse
    {
        $connections = SsoConnection::vouchingFor((string) $request->validated('email'));

        return response()->json([
            'data' => $connections->map(static fn (SsoConnection $connection): array => [
                'id' => $connection->ulid,
                'provider' => $connection->provider->value,
                'display_name' => $connection->display_name,
            ])->values(),
        ]);
    }

    public function metadata(StartSsoLoginRequest $request, StartSsoLoginAction $action, string $connection): JsonResponse
    {
        $model = $this->resolveActive($connection);

        $redirectUri = $request->validated('redirect_uri');
        $redirectUri = is_string($redirectUri) && $redirectUri !== ''
            ? $redirectUri
            : url("/api/v1/sso/{$model->ulid}/callback");

        $start = $action->execute($model, $redirectUri);

        // SAML returns through the IdP's cross-site POST to the ACS, which only
        // carries SameSite=None (hence Secure) cookies; OIDC keeps the session's
        // policy.
        $saml = $model->provider === SsoProvider::Saml;

        return response()
            ->json([
                'data' => [
                    'connection' => $model->ulid,
                    'provider' => $model->provider->value,
                    'authorization_url' => $start->authorizationUrl,
                    'state' => $start->state,
                ],
            ])
            ->withCookie(cookie(
                self::BINDING_COOKIE,
                $start->binding,
                minutes: intdiv(SsoLoginAttempts::TTL_SECONDS, 60),
                path: self::COOKIE_PATH,
                secure: $saml ? true : null,
                httpOnly: true,
                sameSite: $saml ? 'none' : null,
            ));
    }

    public function callback(SsoCallbackRequest $request, CompleteSsoLoginAction $action, string $connection): JsonResponse
    {
        $model = $this->resolveActive($connection);
        $validated = $request->validated();
        $binding = $request->cookie(self::BINDING_COOKIE);

        $result = $action->execute(
            $model,
            (string) $validated['state'],
            is_string($binding) ? $binding : null,
            (string) $validated['code'],
            array_filter([
                'email' => is_string($validated['email'] ?? null) ? $validated['email'] : '',
                'name' => is_string($validated['name'] ?? null) ? $validated['name'] : '',
            ], static fn (string $hint): bool => $hint !== ''),
        );

        $user = $result->user;

        // Establish the first-party session (same contract as password login).
        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // Login semantics: 200 even when the user was just provisioned (the
        // resource's wasRecentlyCreated flag would otherwise force a 201).
        return UserResource::make($user->load('tenants'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_OK)
            ->withoutCookie(self::BINDING_COOKIE, self::COOKIE_PATH);
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
