<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $customer = Customer::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        );

        $orders = $customer->orders()
            ->with('items')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'created_at' => $order->created_at?->format('d.m.Y H:i'),
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => number_format($order->total_cents / 100, 2, '.', ' '),
                'items_count' => $order->items->sum('quantity'),
            ]);

        return Inertia::render('Account/Dashboard', [
            'customer' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'orders_count' => $customer->orders_count,
                'total_spent' => number_format($customer->total_spent_cents / 100, 2, '.', ' '),
            ],
            'orders' => $orders,
        ]);
    }
}
