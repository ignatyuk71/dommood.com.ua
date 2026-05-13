<?php

namespace App\Http\Middleware;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $user->refresh();

        abort_if($user->is_active === false, 403);

        app(SyncAdminRolesAndPermissions::class)->assignLegacyRole($user);

        $hasAdminRole = $user->hasAnyRole(['admin', 'manager'])
            || in_array($user->role, ['admin', 'manager'], true);

        abort_if(! $hasAdminRole, 403);

        return $next($request);
    }
}
