<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingIntegration;
use App\Models\MarketingIntegrationAuditLog;
use App\Services\AdminActivityLogger;
use App\Support\Admin\MarketingIntegrationConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackingSettingsController extends Controller
{
    public function __construct(private readonly MarketingIntegrationConfig $integrations) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.tracking.show', 'google');
    }

    public function show(string $channel): Response
    {
        abort_unless(in_array($channel, $this->integrations->providers(), true), 404);

        $integration = MarketingIntegration::query()
            ->with(['settings', 'credentials'])
            ->where('provider', $channel)
            ->first();

        return Inertia::render('Admin/Settings/Tracking', [
            'activeChannel' => $channel,
            'channels' => $this->channels(),
            'integration' => $this->integrations->integrationPayload($channel, $integration),
            'formSchema' => $this->integrations->formSchema($channel),
        ]);
    }

    public function update(Request $request, string $channel): RedirectResponse
    {
        abort_unless(in_array($channel, $this->integrations->providers(), true), 404);

        $data = $request->validate($this->integrations->rules($channel));
        $actorId = $request->user()?->id;
        $integration = $this->integrations->ensureIntegration($channel, $actorId);
        $changes = $this->integrations->persistSettings($integration, $channel, $data, $actorId);

        MarketingIntegrationAuditLog::query()->create([
            'marketing_integration_id' => $integration->id,
            'action' => 'settings_updated',
            'actor_id' => $actorId,
            'meta' => [
                'provider' => $channel,
                'status_changed' => $changes['status_changed'],
                'mode_changed' => $changes['mode_changed'],
                'settings_changed' => $changes['settings_changed'],
                'credentials' => $changes['credentials'],
            ],
        ]);

        app(AdminActivityLogger::class)->log(
            $request,
            'settings.tracking_updated',
            $integration->refresh(),
            newValues: [
                'provider' => $channel,
                'status' => $integration->status,
                'mode' => $integration->mode,
                'settings_changed' => $changes['settings_changed'],
                'status_changed' => $changes['status_changed'],
                'mode_changed' => $changes['mode_changed'],
                'credentials_changed' => collect($changes['credentials'])->contains(fn (string $changed): bool => $changed !== 'unchanged'),
            ],
            description: 'Менеджер оновив tracking підключення',
        );

        return redirect()
            ->route('admin.settings.tracking.show', $channel)
            ->with('success', 'Tracking підключення збережено');
    }

    private function channels(): array
    {
        return collect(MarketingIntegrationConfig::CHANNELS)
            ->map(fn (array $channel, string $key): array => [
                ...$channel,
                'key' => $key,
                'route' => route('admin.settings.tracking.show', $key),
                'analytics_route' => route('admin.analytics.show', $key),
            ])
            ->values()
            ->all();
    }
}
