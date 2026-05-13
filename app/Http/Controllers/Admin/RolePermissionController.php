<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminActivityLogger;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index(): Response
    {
        app(SyncAdminRolesAndPermissions::class)();

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['admin', 'manager'])
            ->withCount('users')
            ->with([
                'permissions:id,name',
                'users' => fn ($query) => $query
                    ->select('users.id', 'users.name', 'users.email', 'users.role', 'users.is_active', 'users.last_login_at', 'users.created_at')
                    ->orderBy('users.name'),
            ])
            ->get()
            ->sortBy(fn (Role $role): int => match ($role->name) {
                'admin' => 1,
                'manager' => 2,
                default => 99,
            })
            ->values()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->roleLabel($role->name),
                'description' => $this->roleDescription($role->name),
                'users_count' => $role->users_count,
                'locked' => $role->name === 'admin',
                'permissions' => $role->name === 'admin'
                    ? AdminPermissions::all()
                    : $role->permissions->pluck('name')->values()->all(),
                'users' => $role->users->map(fn ($user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => (bool) $user->is_active,
                    'last_login_at' => $user->last_login_at?->format('d.m.Y H:i'),
                    'created_at' => $user->created_at?->format('d.m.Y'),
                ])->values()->all(),
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'groups' => AdminPermissions::groups(),
            'allPermissions' => AdminPermissions::all(),
            'staffTotal' => $roles->sum('users_count'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->name === 'admin', 403);

        $role->load('permissions:id,name');
        $oldPermissions = $role->permissions->pluck('name')->sort()->values()->all();

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(AdminPermissions::all())],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);
        $role->load('permissions:id,name');

        app(AdminActivityLogger::class)->log(
            $request,
            'roles.permissions_updated',
            $role,
            oldValues: ['permissions' => $oldPermissions],
            newValues: ['permissions' => $role->permissions->pluck('name')->sort()->values()->all()],
            description: 'Менеджер оновив доступи ролі',
        );

        return back()->with('success', 'Доступи ролі оновлено');
    }

    public function storeStaff(Request $request): RedirectResponse
    {
        app(SyncAdminRolesAndPermissions::class)();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::in(['admin', 'manager'])],
            'password' => ['required', Password::defaults()],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Працівника створено');
    }

    public function updateStaff(Request $request, User $user): RedirectResponse
    {
        abort_if($user->hasRole('customer') || $user->role === 'customer', 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'manager'])],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($request->user()?->is($user) && ! $data['is_active']) {
            return back()->withErrors(['staff' => 'Не можна вимкнути власний обліковий запис.']);
        }

        if ($request->user()?->is($user) && $data['role'] !== 'admin') {
            return back()->withErrors(['staff' => 'Не можна змінити власну роль адміністратора.']);
        }

        if (
            $user->role === 'admin'
            && (! $data['is_active'] || $data['role'] !== 'admin')
            && ! User::query()
                ->where('id', '!=', $user->id)
                ->where('role', 'admin')
                ->where('is_active', true)
                ->exists()
        ) {
            return back()->withErrors(['staff' => 'Має залишитися хоча б один активний адміністратор.']);
        }

        $user->forceFill([
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ])->save();

        $user->syncRoles([$data['role']]);

        return back()->with('success', 'Працівника оновлено');
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin' => 'Адміністратор',
            'manager' => 'Менеджер',
            default => ucfirst($role),
        };
    }

    private function roleDescription(string $role): string
    {
        return match ($role) {
            'admin' => 'Повний доступ до адмінки, налаштувань і ролей.',
            'manager' => 'Операційна роль для обробки замовлень без доступу до налаштувань.',
            default => 'Кастомна роль.',
        };
    }
}
