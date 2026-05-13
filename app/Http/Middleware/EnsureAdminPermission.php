<?php

namespace App\Http\Middleware;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Support\AdminPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();
        abort_if(! $user, 403);

        $user->refresh();

        abort_if($user->is_active === false, 403);

        $requiredPermissions = array_filter(explode('|', $permissions));

        try {
            app(SyncAdminRolesAndPermissions::class)->assignLegacyRole($user);
        } catch (Throwable) {
            // Під час тестів або першого запуску таблиці можуть ще створюватися.
        }

        if ($user->hasRole('admin') || $user->role === 'admin') {
            return $next($request);
        }

        $legacyDefaults = AdminPermissions::defaultsForRole($user->role);
        $hasLegacyPermission = count(array_intersect($requiredPermissions, $legacyDefaults)) > 0;

        abort_if(! $hasLegacyPermission && ! $user->hasAnyPermission($requiredPermissions), 403);

        return $next($request);
    }
}
