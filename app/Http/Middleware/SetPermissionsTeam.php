<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the spatie permissions team id to the authenticated user's tenant, so
 * team-scoped roles resolve correctly. Super Admins (tenant_id null) resolve
 * against global roles.
 */
class SetPermissionsTeam
{
    public function __construct(private PermissionRegistrar $registrar) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $this->registrar->setPermissionsTeamId($user->tenant_id);
        }

        return $next($request);
    }
}
