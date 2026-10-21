<?php

namespace Tests\Feature\Admin;

use App\Actions\Admin\SyncAdminRolesAndPermissions;
use App\Models\AnalyticsEvent;
use App\Models\MarketingEventOutbox;
use App\Models\MarketingIntegration;
use App\Models\MarketingIntegrationCredential;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_meta_integration_and_secret_is_masked(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $token = 'EAAG-secret-token-1234567890';

        $this->actingAs($admin)
            ->put(route('admin.settings.tracking.update', 'meta'), [
                'status' => 'active',
                'mode' => 'test',
                'send_client' => true,
                'send_server' => true,
                'pixel_id' => '123456789',
                'access_token' => $token,
                'test_event_code' => 'TEST1234',
            ])
            ->assertRedirect(route('admin.settings.tracking.show', 'meta'));

        $integration = MarketingIntegration::query()
            ->with(['settings', 'credentials'])
            ->where('provider', 'meta')
            ->firstOrFail();

        $this->assertSame('active', $integration->status);
        $this->assertSame('test', $integration->mode);
        $this->assertSame('123456789', $integration->settings->settings['pixel_id']);
        $this->assertTrue($integration->settings->settings['send_client']);
        $this->assertTrue($integration->settings->settings['send_server']);

        $credential = MarketingIntegrationCredential::query()
            ->where('marketing_integration_id', $integration->id)
            ->where('secret_type', 'access_token')
            ->firstOrFail();

        $rawSecret = DB::table('marketing_integration_credentials')
            ->whereKey($credential->id)
            ->value('secret_value');

        $this->assertNotSame($token, $rawSecret);
        $this->assertSame($token, $credential->secret_value);
        $this->assertSame('7890', $credential->secret_last_four);

        $this->actingAs($admin)
            ->get(route('admin.analytics.show', 'meta'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Analytics/Index')
                ->where('activeChannel', 'meta')
                ->where('integration.configured', true)
                ->where('integration.credentials.access_token.exists', true)
                ->where('integration.credentials.access_token.masked', '••••7890')
            );

        $this->actingAs($admin)
            ->get(route('admin.settings.tracking.show', 'meta'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Tracking')
                ->where('activeChannel', 'meta')
                ->where('integration.configured', true)
                ->where('integration.credentials.access_token.exists', true)
            );
    }

    public function test_empty_secret_does_not_replace_existing_secret_and_clear_removes_it(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.settings.tracking.update', 'tiktok'), [
                'status' => 'active',
                'mode' => 'prod',
                'send_client' => true,
                'send_server' => true,
                'pixel_id' => 'TT-PIXEL',
                'access_token' => 'tt-token-1111',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->put(route('admin.settings.tracking.update', 'tiktok'), [
                'status' => 'active',
                'mode' => 'prod',
                'send_client' => true,
                'send_server' => true,
                'pixel_id' => 'TT-PIXEL',
                'access_token' => '',
            ])
            ->assertRedirect();

        $integration = MarketingIntegration::query()->where('provider', 'tiktok')->firstOrFail();

        $this->assertSame(
            'tt-token-1111',
            MarketingIntegrationCredential::query()
                ->where('marketing_integration_id', $integration->id)
                ->where('secret_type', 'access_token')
                ->firstOrFail()
                ->secret_value
        );

        $this->actingAs($admin)
            ->put(route('admin.settings.tracking.update', 'tiktok'), [
                'status' => 'active',
                'mode' => 'prod',
                'send_client' => true,
                'send_server' => true,
                'pixel_id' => 'TT-PIXEL',
                'access_token' => '',
                'access_token_clear' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('marketing_integration_credentials', [
            'marketing_integration_id' => $integration->id,
            'secret_type' => 'access_token',
        ]);
    }

    public function test_manager_cannot_update_analytics_integration_without_permission(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $manager = User::factory()->create(['role' => 'manager']);
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->put(route('admin.settings.tracking.update', 'google'), [
                'status' => 'active',
                'mode' => 'prod',
            ])
            ->assertForbidden();
    }

    public function test_admin_analytics_page_contains_chart_metrics_funnel_and_event_log(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $integration = MarketingIntegration::query()->create([
            'provider' => 'meta',
            'status' => 'active',
            'mode' => 'test',
        ]);

        $now = CarbonImmutable::create(2026, 5, 26, 8, 18, 6, 'UTC');
        $this->travelTo($now);

        AnalyticsEvent::query()->create([
            'event_name' => 'ViewContent',
            'event_id' => 'evt-view-1',
            'source' => 'meta',
            'session_id' => 'session-a',
            'value_cents' => 0,
            'occurred_at' => $now->subDays(2),
        ]);
        AnalyticsEvent::query()->create([
            'event_name' => 'AddToCart',
            'event_id' => 'evt-cart-1',
            'source' => 'meta',
            'session_id' => 'session-a',
            'value_cents' => 0,
            'occurred_at' => $now->subDay(),
        ]);
        AnalyticsEvent::query()->create([
            'event_name' => 'Purchase',
            'event_id' => 'evt-purchase-1',
            'source' => 'meta',
            'session_id' => 'session-b',
            'value_cents' => 129900,
            'occurred_at' => $now,
        ]);

        MarketingEventOutbox::query()->create([
            'marketing_integration_id' => $integration->id,
            'provider' => 'meta',
            'event_name' => 'Purchase',
            'event_id' => 'evt-purchase-1',
            'transport' => 'server',
            'status' => 'sent',
            'attempts' => 1,
            'sent_at' => $now,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.show', 'meta'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Analytics/Index')
                ->where('analytics.period.label', 'Останні 30 днів')
                ->where('analytics.metrics.0.value', 2)
                ->where('analytics.metrics.1.value', 1)
                ->where('analytics.metrics.2.value', 129900)
                ->where('analytics.funnel.0.count', 1)
                ->where('analytics.funnel.1.count', 1)
                ->where('analytics.funnel.3.count', 1)
                ->where('analytics.eventLog.0.date', '2026-05-26 11:18:06')
                ->where('analytics.chart.interval', 'day')
                ->has('analytics.chart.labels', 30)
                ->where('analytics.chart.labels.27', '24.05')
                ->where('analytics.chart.labels.28', '25.05')
                ->where('analytics.chart.labels.29', '26.05')
                ->where('analytics.chart.series.0.values.27', 1)
                ->where('analytics.chart.series.1.values.28', 1)
                ->where('analytics.chart.series.3.values.29', 1)
                ->has('analytics.chart.series', 4)
                ->has('analytics.eventLog', 4)
            );

        $this->actingAs($admin)
            ->get(route('admin.analytics.show', [
                'channel' => 'meta',
                'start_date' => $now->toDateString(),
                'end_date' => $now->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.period.label', 'Вибраний період')
                ->where('analytics.metrics.0.value', 1)
                ->where('analytics.metrics.1.value', 1)
                ->where('analytics.metrics.2.value', 129900)
                ->where('analytics.chart.interval', 'hour')
                ->has('analytics.chart.labels', 24)
                ->where('analytics.chart.series.3.values.11', 1)
                ->has('analytics.eventLog', 2)
            );
    }

    #[DataProvider('analyticsChannels')]
    public function test_single_day_chart_groups_events_by_local_hour(string $channel): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->travelTo(CarbonImmutable::parse('2026-10-21 09:00:00', 'UTC'));

        // UTC-час перевіряє межі київської доби та потрапляння в потрібну годину.
        foreach ([
            ['ViewContent', '2026-10-20 20:59:59'],
            ['ViewContent', '2026-10-20 21:00:00'],
            ['view_item', '2026-10-20 21:59:59'],
            ['AddToCart', '2026-10-20 22:00:00'],
            ['InitiateCheckout', '2026-10-21 08:15:00'],
            ['Purchase', '2026-10-21 20:59:59'],
            ['Purchase', '2026-10-21 21:00:00'],
        ] as $index => [$eventName, $occurredAt]) {
            AnalyticsEvent::query()->create([
                'event_name' => $eventName,
                'event_id' => 'hourly-'.$index,
                'source' => $channel,
                'occurred_at' => $occurredAt,
            ]);
        }

        $zeroes = array_fill(0, 24, 0);

        $this->actingAs($admin)
            ->get(route('admin.analytics.show', [
                'channel' => $channel,
                'start_date' => '2026-10-21',
                'end_date' => '2026-10-21',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.chart.interval', 'hour')
                ->where('analytics.chart.labels', array_map(fn (int $hour): string => sprintf('%02d:00', $hour), range(0, 23)))
                ->where('analytics.chart.series.0.values', array_replace($zeroes, [0 => 2]))
                ->where('analytics.chart.series.1.values', array_replace($zeroes, [1 => 1]))
                ->where('analytics.chart.series.2.values', array_replace($zeroes, [11 => 1]))
                ->where('analytics.chart.series.3.values', array_replace($zeroes, [23 => 1]))
                ->has('analytics.eventLog', 5)
            );
    }

    public static function analyticsChannels(): array
    {
        return [
            'Google' => ['google'],
            'TikTok' => ['tiktok'],
            'Meta' => ['meta'],
        ];
    }

    public function test_single_day_without_events_keeps_all_hourly_buckets(): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.analytics.show', [
                'channel' => 'meta',
                'start_date' => '2026-10-20',
                'end_date' => '2026-10-20',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.chart.interval', 'hour')
                ->has('analytics.chart.labels', 24)
                ->where('analytics.chart.labels.0', '00:00')
                ->where('analytics.chart.labels.23', '23:00')
                ->has('analytics.chart.series', 4)
                ->where('analytics.chart.series.0.values', array_fill(0, 24, 0))
                ->where('analytics.chart.series.1.values', array_fill(0, 24, 0))
                ->where('analytics.chart.series.2.values', array_fill(0, 24, 0))
                ->where('analytics.chart.series.3.values', array_fill(0, 24, 0))
            );
    }

    #[DataProvider('clockChangeDays')]
    public function test_hourly_chart_handles_clock_changes(string $day, int $hours, string $firstEvent, string $secondEvent): void
    {
        app(SyncAdminRolesAndPermissions::class)(force: true);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        foreach ([$firstEvent, $secondEvent] as $index => $occurredAt) {
            AnalyticsEvent::query()->create([
                'event_name' => 'ViewContent',
                'event_id' => 'clock-change-'.$index,
                'source' => 'meta',
                'occurred_at' => $occurredAt,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('admin.analytics.show', [
                'channel' => 'meta',
                'start_date' => $day,
                'end_date' => $day,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('analytics.chart.interval', 'hour')
                ->has('analytics.chart.labels', $hours)
                ->has('analytics.chart.series.0.values', $hours)
                ->where('analytics.chart.series.0.values.3', 1)
                ->where('analytics.chart.series.0.values.4', 1)
                ->where('analytics.chart.series.0.values', fn ($values): bool => $values->sum() === 2)
            );
    }

    public static function clockChangeDays(): array
    {
        return [
            'Літній час' => ['2026-03-29', 23, '2026-03-29 01:15:00', '2026-03-29 02:15:00'],
            'Зимовий час' => ['2026-10-25', 25, '2026-10-25 00:15:00', '2026-10-25 01:15:00'],
        ];
    }
}
