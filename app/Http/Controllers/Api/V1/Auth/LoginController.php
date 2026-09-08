<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, AuditLogger $audit): UserResource
    {
        $credentials = $request->only('email', 'password');
        $remember = (bool) $request->boolean('remember');

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        $audit->log('auth.login', actor: $user, auditable: $user);

        return UserResource::make($user->load('tenants'));
    }
}
