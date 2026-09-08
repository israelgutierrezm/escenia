<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('tenants'));
    }
}
