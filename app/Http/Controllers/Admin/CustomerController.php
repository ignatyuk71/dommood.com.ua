<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $customers = Customer::query()
            ->with('user:id,name,email,phone,is_active,last_login_at,created_at')
            ->withCount('orders as real_orders_count')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('last_order_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Customer $customer): array => $this->serializeCustomer($customer));

        $registeredCustomers = User::role('customer')->count();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
            ],
            'summary' => [
                'total' => Customer::query()->count(),
                'registered' => $registeredCustomers,
                'withOrders' => Customer::query()->where('orders_count', '>', 0)->count(),
            ],
        ]);
    }

    private function serializeCustomer(Customer $customer): array
    {
        $fullName = trim(implode(' ', array_filter([$customer->first_name, $customer->last_name])));

        return [
            'id' => $customer->id,
            'name' => $fullName !== '' ? $fullName : ($customer->user?->name ?? 'Клієнт без імені'),
            'phone' => $customer->phone ?? $customer->user?->phone,
            'email' => $customer->email ?? $customer->user?->email,
            'orders_count' => $customer->real_orders_count ?: $customer->orders_count,
            'total_spent' => number_format($customer->total_spent_cents / 100, 2, '.', ' '),
            'source' => $customer->source ?: $customer->utm_source ?: 'Інше',
            'first_order_at' => $customer->first_order_at?->format('d.m.Y H:i'),
            'last_order_at' => $customer->last_order_at?->format('d.m.Y H:i'),
            'registered' => (bool) $customer->user_id,
            'is_active' => $customer->user?->is_active ?? true,
            'last_login_at' => $customer->user?->last_login_at?->format('d.m.Y H:i'),
        ];
    }
}
