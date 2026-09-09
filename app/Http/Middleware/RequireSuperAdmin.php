<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the platform super-admin surface (system-wide settings). The flag is set
 * out of band (the escenia:super-admin command), never through the tenant API.
 */
class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->isSuperAdmin(),
            Response::HTTP_FORBIDDEN,
            'Super-admin access required.',
        );

        return $next($request);
    }
}
