<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Support\AdminPermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncAdminRolesAndPermissions
{
    private static bool $synced = false;

    public function __invoke(bool $force = false): void
    {
        if (self::$synced && ! $force && Role::query()->where('name', 'admin')->where('guard_name', 'web')->exists()) {
            return;
        }

        foreach (AdminPermissions::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (['admin', 'manager', 'customer'] as $roleName) {
            $role = Role::findOrCreate($roleName, 'web');

            if ($roleName === 'admin' || $force || ! $role->permissions()->exists()) {
                $role->syncPermissions(AdminPermissions::defaultsForRole($roleName));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        self::$synced = true;
    }

    public function assignLegacyRole(User $user): void
    {
        $this->__invoke();

        if ($user->roles()->exists()) {
            return;
        }

        $legacyRole = $user->role ?? $user->fresh()?->role;

        $legacyRole = in_array($legacyRole, ['admin', 'manager', 'customer'], true)
            ? $legacyRole
            : 'customer';

        $user->assignRole($legacyRole);
    }
}
