<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the authenticated user id to the log context once auth has resolved.
 */
class ShareUserContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            Context::add('user_id', $user->getKey());
        }

        return $next($request);
    }
}
