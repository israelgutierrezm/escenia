<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enterprise;

use App\Application\Enterprise\Actions\CompleteSsoLoginAction;
use App\Application\Enterprise\SsoLoginAttempts;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Http\Controllers\Controller;
use App\Infrastructure\Enterprise\Sso\SamlIdentityProvider;
use App\Rules\FirstPartyUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SAML service-provider endpoints. `acs` is where the IdP's browser POST lands
 * (a session route, exempt from CSRF — it carries a signed SAMLResponse, and the
 * login is bound to the browser by the cookie and to the attempt by
 * RelayState); it completes the login and sends the browser back to the SPA it
 * started from. `metadata` publishes Escenia's SP metadata for the IdP admin.
 */
class SamlController extends Controller
{
    public function acs(Request $request, CompleteSsoLoginAction $action, SsoLoginAttempts $attempts, string $connection): Response
    {
        $model = $this->resolveSaml($connection);
        $state = (string) $request->input('RelayState', '');
        $returnUrl = $attempts->returnUrl($state);
        $binding = $request->cookie(SsoController::BINDING_COOKIE);

        try {
            $user = $action->execute(
                $model,
                $state,
                is_string($binding) ? $binding : null,
                (string) $request->input('SAMLResponse', ''),
            );
        } catch (SsoAuthenticationException) {
            return $this->backToApp($returnUrl, ['error' => 'sso_failed']);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->backToApp($returnUrl, ['status' => 'ok'])
            ->withoutCookie(SsoController::BINDING_COOKIE, SsoController::COOKIE_PATH);
    }

    public function metadata(SamlIdentityProvider $saml, string $connection): Response
    {
        return response($saml->serviceProviderMetadata($this->resolveSaml($connection)), Response::HTTP_OK, [
            'Content-Type' => 'application/samlmetadata+xml',
        ]);
    }

    /**
     * Back to the first-party app the login started from, or a plain page when
     * the attempt is unknown (no trustworthy place to redirect to).
     *
     * @param  array<string, string>  $query
     */
    private function backToApp(?string $returnUrl, array $query): RedirectResponse|HttpResponse
    {
        if ($returnUrl === null || ! FirstPartyUrl::matches($returnUrl)) {
            $ok = ! isset($query['error']);

            return response()->view('sso.result', ['ok' => $ok], $ok ? Response::HTTP_OK : Response::HTTP_UNAUTHORIZED);
        }

        $separator = str_contains($returnUrl, '?') ? '&' : '?';

        return new RedirectResponse($returnUrl.$separator.http_build_query($query));
    }

    private function resolveSaml(string $connection): SsoConnection
    {
        $model = SsoConnection::query()
            ->withoutGlobalScopes()
            ->where('ulid', $connection)
            ->where('provider', SsoProvider::Saml)
            ->where('is_active', true)
            ->first();

        if ($model === null) {
            abort(Response::HTTP_NOT_FOUND, 'SSO connection not found.');
        }

        return $model;
    }
}
