<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Identity\Actions\RegisterUserAction;
use App\Application\Identity\DTOs\RegisterUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        /** @var array{name: string, email: string, password: string, tenant_name: string} $data */
        $data = $request->validated();

        $user = $action->execute(RegisterUserData::fromArray($data));

        // Establish a first-party session for the SPA (cookie mode). Safe when
        // the request has no session (e.g. token clients / tests).
        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return UserResource::make($user->load('tenants'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
