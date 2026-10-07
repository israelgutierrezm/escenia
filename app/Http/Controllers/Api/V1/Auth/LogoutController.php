<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Enterprise\Actions\StartSingleLogoutAction;
use App\Http\Controllers\Api\V1\Enterprise\SamlController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LogoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Ends the first-party session. When SSO opened it and the IdP supports single
 * logout (ADR-036), the answer also carries `sso_logout_url`: the SPA sends the
 * browser there so the IdP session ends too, and the IdP returns it to our SLO
 * endpoint, which forwards it to `return_to`.
 */
class LogoutController extends Controller
{
    public function __invoke(LogoutRequest $request, StartSingleLogoutAction $singleLogout): Response|JsonResponse
    {
        $ssoSession = $request->hasSession() ? $request->session()->get(SamlController::SESSION_KEY) : null;

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $redirect = is_array($ssoSession) ? $singleLogout->execute($ssoSession) : null;

        if ($redirect === null || ! $request->hasSession()) {
            return response()->noContent();
        }

        // Remembered in the fresh session: the IdP's answer must reference this request.
        $request->session()->put(SamlController::LOGOUT_KEY, [
            'request_id' => $redirect->requestId,
            'return_url' => $request->validated('return_to'),
        ]);

        return response()->json(['data' => ['sso_logout_url' => $redirect->url]]);
    }
}
