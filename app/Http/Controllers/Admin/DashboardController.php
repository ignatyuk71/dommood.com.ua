<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $endDate = CarbonImmutable::now()->endOfDay();
        $startDate = $endDate->subDays(29)->startOfDay();

        $orders = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get([
                'id',
                'customer_id',
                'customer_phone',
                'total_cents',
                'source',
                'utm_source',
                'created_at',
            ]);

        $orderIds = $orders->pluck('id');
        $ordersCount = $orders->count();
        $revenueCents = (int) $orders->sum('total_cents');
        $soldUnits = $orderIds->isEmpty()
            ? 0
            : (int) OrderItem::query()->whereIn('order_id', $orderIds)->sum('quantity');

        $uniqueCustomers = $orders
            ->map(fn (Order $order): ?string => $order->customer_id
                ? 'customer-'.$order->customer_id
                : ($order->customer_phone ? 'phone-'.$order->customer_phone : null))
            ->filter()
            ->unique()
            ->count();

        return Inertia::render('Admin/Dashboard', [
            'dashboard' => [
                'period' => [
                    'label' => $this->periodLabel($startDate, $endDate),
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                ],
                'stats' => [
                    'orders_count' => $ordersCount,
                    'revenue_cents' => $revenueCents,
                    'average_order_cents' => $ordersCount > 0 ? intdiv($revenueCents, $ordersCount) : 0,
                    'unique_customers' => $uniqueCustomers,
                    'sold_units' => $soldUnits,
                    'active_products' => Product::query()->where('status', 'active')->count(),
                    'customers_total' => Customer::query()->count(),
                ],
                'chart' => $this->dailyChart($orders, $startDate, $endDate),
                'sources' => $this->sources($orders),
            ],
        ]);
    }

    private function dailyChart($orders, CarbonImmutable $startDate, CarbonImmutable $endDate): array
    {
        $grouped = $orders->groupBy(fn (Order $order): string => $order->created_at->toDateString());
        $labels = [];
        $ordersSeries = [];
        $revenueSeries = [];

        for ($date = $startDate; $date->lessThanOrEqualTo($endDate); $date = $date->addDay()) {
            $dateKey = $date->toDateString();
            $dayOrders = $grouped->get($dateKey, collect());

            $labels[] = $date->format('d.m');
            $ordersSeries[] = $dayOrders->count();
            $revenueSeries[] = round(((int) $dayOrders->sum('total_cents')) / 100, 2);
        }

        return [
            'labels' => $labels,
            'orders' => $ordersSeries,
            'revenue' => $revenueSeries,
        ];
    }

    private function sources($orders): array
    {
        $sourceLabels = ['Meta', 'TikTok', 'Google', 'Інше'];
        $sourceTotals = array_fill_keys($sourceLabels, [
            'orders' => 0,
            'revenue_cents' => 0,
        ]);

        foreach ($orders as $order) {
            $source = $this->normalizeSource($order->utm_source ?: $order->source);
            $sourceTotals[$source] = [
                'orders' => $sourceTotals[$source]['orders'] + 1,
                'revenue_cents' => $sourceTotals[$source]['revenue_cents'] + (int) $order->total_cents,
            ];
        }

        return collect($sourceLabels)
            ->map(fn (string $label): array => [
                'label' => $label,
                'orders' => $sourceTotals[$label]['orders'],
                'revenue_cents' => $sourceTotals[$label]['revenue_cents'],
            ])
            ->values()
            ->all();
    }

    private function normalizeSource(?string $source): string
    {
        $value = str($source ?: '')->lower()->trim()->toString();

        return match (true) {
            str_contains($value, 'meta'),
            str_contains($value, 'facebook'),
            str_contains($value, 'instagram'),
            str_contains($value, 'fb') => 'Meta',
            str_contains($value, 'tiktok'),
            str_contains($value, 'tik') => 'TikTok',
            str_contains($value, 'google'),
            str_contains($value, 'gads'),
            str_contains($value, 'cpc') => 'Google',
            default => 'Інше',
        };
    }

    private function periodLabel(CarbonImmutable $startDate, CarbonImmutable $endDate): string
    {
        return sprintf(
            'Останні 30 дн.: %s — %s',
            $this->shortDate($startDate),
            $this->shortDate($endDate),
        );
    }

    private function shortDate(CarbonImmutable $date): string
    {
        $months = [
            1 => 'січ',
            2 => 'лют',
            3 => 'бер',
            4 => 'кві',
            5 => 'тра',
            6 => 'чер',
            7 => 'лип',
            8 => 'сер',
            9 => 'вер',
            10 => 'жов',
            11 => 'лис',
            12 => 'гру',
        ];

        return $date->day.' '.$months[$date->month].' '.$date->year;
    }
}
