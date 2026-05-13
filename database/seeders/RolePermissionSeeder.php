<?php

namespace Database\Seeders;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $firstUser = User::query()->oldest('id')->first();

        if ($firstUser && ! User::role('admin')->exists()) {
            $firstUser->assignRole('admin');
            $firstUser->forceFill(['role' => 'admin'])->save();
        }

        User::query()
            ->whereDoesntHave('roles')
            ->get()
            ->each(function (User $user): void {
                $role = in_array($user->role, ['admin', 'manager', 'customer'], true)
                    ? $user->role
                    : 'customer';

                $user->assignRole($role);
            });
    }
}
