<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'roles' => fn () => $user?->getRoleNames()->values()->all() ?? [],
                'permissions' => fn () => $user?->getAllPermissions()->pluck('name')->values()->all() ?? [],
            ],
            'adminStats' => fn () => [
                'active_orders_count' => $this->activeOrdersCount($request),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }

    private function activeOrdersCount(Request $request): int
    {
        $user = $request->user();

        if (! $user || ! $request->is('admin*')) {
            return 0;
        }

        try {
            if (! $user->can('admin.orders.view')) {
                return 0;
            }

            return Order::query()
                ->whereNotIn('status', ['completed', 'cancelled', 'returned'])
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
