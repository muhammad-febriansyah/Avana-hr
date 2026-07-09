<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the `/platform/*` panel to Super Admins (platform users with
 * `tenant_id = null`). Tenant users are denied even if they somehow hold a
 * `platform.*` permission.
 */
class EnsurePlatformAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || $user->tenant_id !== null, 403);

        return $next($request);
    }
}
