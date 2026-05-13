<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\MarketingEventOutbox;
use App\Models\MarketingIntegration;
use App\Support\Admin\MarketingIntegrationConfig;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(private readonly MarketingIntegrationConfig $integrations)
    {
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.analytics.show', 'google');
    }

    public function show(Request $request, string $channel): Response
    {
        abort_unless(in_array($channel, $this->integrations->providers(), true), 404);

        $period = $this->dateRange($request);
        $integration = MarketingIntegration::query()
            ->with(['settings', 'credentials'])
            ->where('provider', $channel)
            ->first();

        return Inertia::render('Admin/Analytics/Index', [
            'activeChannel' => $channel,
            'channels' => $this->channels($request),
            'integration' => $this->integrations->integrationPayload($channel, $integration),
            'analytics' => $this->analyticsPayload($channel, $integration, $period),
            'trackingPlan' => [
                'UTM з рекламних кампаній зберігаємо в замовленнях і профілі клієнта.',
                'Client-side події відправляємо через GTM/Pixel на storefront.',
                'Server-side Purchase дублюємо з backend з event_id для дедуплікації.',
                'ROAS рахуємо по витратах з рекламних API і доходу з MySQL.',
            ],
        ]);
    }

    private function channels(Request $request): array
    {
        $query = $request->only(['start_date', 'end_date']);

        return collect(MarketingIntegrationConfig::CHANNELS)
            ->map(fn (array $channel, string $key): array => [
                ...$channel,
                'key' => $key,
                'route' => route('admin.analytics.show', [$key, ...$query]),
            ])
            ->values()
            ->all();
    }

    private function analyticsPayload(string $provider, ?MarketingIntegration $integration, array $period): array
    {
        $start = $period['start'];
        $end = $period['end'];
        $sourceAliases = $this->sourceAliases($provider);
        $events = AnalyticsEvent::query()
            ->whereIn('source', $sourceAliases)
            ->whereBetween('occurred_at', [$start, $end])
            ->orderByDesc('occurred_at')
            ->get([
                'id',
                'event_name',
                'event_id',
                'source',
                'channel',
                'session_id',
                'order_id',
                'value_cents',
                'occurred_at',
                'created_at',
            ]);

        $outbox = MarketingEventOutbox::query()
            ->when(
                $integration,
                fn ($query) => $query->where('marketing_integration_id', $integration->id),
                fn ($query) => $query->where('provider', $provider),
            )
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'provider',
                'event_name',
                'event_id',
                'transport',
                'status',
                'attempts',
                'last_error',
                'created_at',
                'sent_at',
            ]);

        $normalizedEvents = $events->map(fn (AnalyticsEvent $event): array => [
            'event' => $this->normalizeEventName($event->event_name),
            'raw_event' => $event->event_name,
            'event_id' => $event->event_id,
            'source' => $event->source,
            'channel' => $event->channel,
            'session_id' => $event->session_id,
            'order_id' => $event->order_id,
            'value_cents' => (int) $event->value_cents,
            'occurred_at' => $event->occurred_at,
        ]);

        $chartKeys = ['view_item', 'add_to_cart', 'begin_checkout', 'purchase'];
        $chartLabels = [];
        $chartBuckets = [];
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $day = $cursor->toDateString();
            $chartLabels[] = $cursor->format('d.m');
            $chartBuckets[$day] = array_fill_keys($chartKeys, 0);
            $cursor = $cursor->addDay();
        }

        foreach ($normalizedEvents as $event) {
            $day = $event['occurred_at']?->toDateString();
            if ($day && isset($chartBuckets[$day][$event['event']])) {
                $chartBuckets[$day][$event['event']]++;
            }
        }

        $funnel = $this->funnelPayload($normalizedEvents);
        $purchaseEvents = $normalizedEvents->where('event', 'purchase');
        $metrics = [
            [
                'label' => 'Сесії',
                'value' => $normalizedEvents->pluck('session_id')->filter()->unique()->count(),
                'format' => 'number',
                'hint' => 'Унікальні session_id з подій',
            ],
            [
                'label' => 'Покупки',
                'value' => $purchaseEvents->count(),
                'format' => 'number',
                'hint' => 'Purchase події за період',
            ],
            [
                'label' => 'Дохід',
                'value' => $purchaseEvents->sum('value_cents'),
                'format' => 'money_cents',
                'hint' => 'Сума value_cents у purchase',
            ],
            [
                'label' => 'Server sent',
                'value' => $outbox->where('status', 'sent')->count(),
                'format' => 'number',
                'hint' => 'Успішні server-side відправки',
            ],
            [
                'label' => 'Помилки',
                'value' => $outbox->where('status', 'failed')->count(),
                'format' => 'number',
                'hint' => 'Failed у marketing outbox',
            ],
        ];

        return [
            'period' => [
                'label' => $period['label'],
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'chart' => [
                'labels' => $chartLabels,
                'series' => collect($chartKeys)->map(fn (string $key): array => [
                    'key' => $key,
                    'label' => $this->eventLabel($key),
                    'color' => $this->eventColor($key),
                    'values' => collect($chartBuckets)->map(fn (array $bucket): int => $bucket[$key])->values()->all(),
                ])->all(),
            ],
            'metrics' => $metrics,
            'funnel' => $funnel,
            'eventLog' => $this->eventLogPayload($normalizedEvents, $outbox),
        ];
    }

    private function funnelPayload($events): array
    {
        $steps = [
            'view_item' => 'Перегляд товару',
            'add_to_cart' => 'Додано в корзину',
            'begin_checkout' => 'Checkout',
            'purchase' => 'Покупка',
        ];
        $base = max(1, (int) $events->where('event', 'view_item')->count());

        return collect($steps)->map(function (string $label, string $key) use ($events, $base): array {
            $count = (int) $events->where('event', $key)->count();

            return [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'rate' => $key === 'view_item' && $count > 0 ? 100 : min(100, round(($count / $base) * 100, 1)),
            ];
        })->values()->all();
    }

    private function eventLogPayload($analyticsEvents, $outbox): array
    {
        $browserRows = $analyticsEvents->map(fn (array $event): array => [
            'id' => 'event-'.$event['event_id'].'-'.$event['raw_event'],
            'date' => $event['occurred_at']?->toDateTimeString(),
            'event_name' => $this->eventLabel($event['event']),
            'event_id' => $event['event_id'] ?: '—',
            'transport' => 'browser',
            'status' => 'recorded',
            'attempts' => 0,
            'message' => 'Записано в analytics_events',
        ]);

        $serverRows = $outbox->map(fn (MarketingEventOutbox $event): array => [
            'id' => 'outbox-'.$event->id,
            'date' => ($event->sent_at ?? $event->created_at)?->toDateTimeString(),
            'event_name' => $this->eventLabel($this->normalizeEventName($event->event_name)),
            'event_id' => $event->event_id ?: '—',
            'transport' => $event->transport,
            'status' => $event->status,
            'attempts' => $event->attempts,
            'message' => $event->last_error ?: ($event->status === 'sent' ? 'Відправлено' : 'Очікує обробки'),
        ]);

        return $browserRows
            ->merge($serverRows)
            ->sortByDesc('date')
            ->take(20)
            ->values()
            ->all();
    }

    private function sourceAliases(string $provider): array
    {
        return $this->integrations->sourceAliases($provider);
    }

    private function normalizeEventName(?string $eventName): string
    {
        return match (mb_strtolower((string) $eventName)) {
            'pageview', 'page_view' => 'page_view',
            'viewcontent', 'view_item', 'view_item_list' => 'view_item',
            'addtocart', 'add_to_cart' => 'add_to_cart',
            'initiatecheckout', 'begin_checkout' => 'begin_checkout',
            'purchase' => 'purchase',
            default => mb_strtolower((string) $eventName),
        };
    }

    private function eventLabel(string $eventName): string
    {
        return match ($eventName) {
            'page_view' => 'PageView',
            'view_item' => 'ViewContent',
            'add_to_cart' => 'AddToCart',
            'begin_checkout' => 'Checkout',
            'purchase' => 'Purchase',
            default => $eventName,
        };
    }

    private function eventColor(string $eventName): string
    {
        return match ($eventName) {
            'view_item' => '#2563eb',
            'add_to_cart' => '#8b5cf6',
            'begin_checkout' => '#f59e0b',
            'purchase' => '#16a34a',
            default => '#64748b',
        };
    }

    private function dateRange(Request $request): array
    {
        $defaultEnd = CarbonImmutable::now()->endOfDay();
        $defaultStart = $defaultEnd->subDays(29)->startOfDay();
        $startInput = $request->query('start_date');
        $endInput = $request->query('end_date');

        if (! is_string($startInput) || ! is_string($endInput)) {
            return [
                'label' => 'Останні 30 днів',
                'start' => $defaultStart,
                'end' => $defaultEnd,
            ];
        }

        try {
            $start = CarbonImmutable::createFromFormat('Y-m-d', $startInput)->startOfDay();
            $end = CarbonImmutable::createFromFormat('Y-m-d', $endInput)->endOfDay();
        } catch (\Throwable) {
            return [
                'label' => 'Останні 30 днів',
                'start' => $defaultStart,
                'end' => $defaultEnd,
            ];
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        if ($start->diffInDays($end) > 370) {
            $start = $end->subDays(370)->startOfDay();
        }

        return [
            'label' => 'Вибраний період',
            'start' => $start,
            'end' => $end,
        ];
    }
}
