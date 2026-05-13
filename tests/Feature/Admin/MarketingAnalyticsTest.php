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

        $now = CarbonImmutable::now();

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
                ->has('analytics.eventLog', 2)
            );
    }
}
