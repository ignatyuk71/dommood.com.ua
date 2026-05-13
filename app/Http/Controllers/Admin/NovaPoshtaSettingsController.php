<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingProviderSetting;
use App\Services\Shipping\NovaPoshtaApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NovaPoshtaSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Settings/NovaPoshta', [
            'settings' => $this->settings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['boolean'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'api_url' => ['required', 'url', 'max:255'],
            'sender_phone' => ['nullable', 'string', 'max:40'],
            'sender_city_ref' => ['nullable', 'string', 'max:120'],
            'sender_city_name' => ['nullable', 'string', 'max:255'],
            'sender_warehouse_ref' => ['nullable', 'string', 'max:120'],
            'sender_warehouse_name' => ['nullable', 'string', 'max:255'],
            'sender_ref' => ['nullable', 'string', 'max:120'],
            'contact_ref' => ['nullable', 'string', 'max:120'],
            'default_weight' => ['nullable', 'numeric', 'min:0.1', 'max:1000'],
        ]);

        $provider = ShippingProviderSetting::query()
            ->firstOrNew(['code' => 'nova_poshta']);
        $settings = $provider->exists ? ($provider->settings ?? []) : [];
        $apiKey = trim((string) ($data['api_key'] ?? ''));

        $settings = array_merge($settings, [
            'api_url' => $data['api_url'],
            'sender_phone' => $this->nullableString($data['sender_phone'] ?? null),
            'sender_city_ref' => $this->nullableString($data['sender_city_ref'] ?? null),
            'sender_city_name' => $this->nullableString($data['sender_city_name'] ?? null),
            'sender_warehouse_ref' => $this->nullableString($data['sender_warehouse_ref'] ?? null),
            'sender_warehouse_name' => $this->nullableString($data['sender_warehouse_name'] ?? null),
            'sender_ref' => $this->nullableString($data['sender_ref'] ?? null),
            'contact_ref' => $this->nullableString($data['contact_ref'] ?? null),
            'default_weight' => (float) ($data['default_weight'] ?? 1),
        ]);

        if ($apiKey !== '') {
            $settings['api_key'] = $apiKey;
        }

        $provider->fill([
            'name' => 'Нова пошта',
            'settings' => $settings,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'sort_order' => 10,
        ])->save();

        app()->forgetInstance(NovaPoshtaApi::class);

        return redirect()
            ->route('admin.settings.nova-poshta.edit')
            ->with('success', 'Налаштування Нової пошти збережено');
    }

    public function syncSender(NovaPoshtaApi $novaPoshta): JsonResponse
    {
        $response = $novaPoshta->findSenderCounterparties();
        $error = $this->providerError($response);

        if ($error) {
            return response()->json(['error' => $error], 422);
        }

        $sender = collect($response['data'] ?? [])
            ->first(fn (array $row): bool => filled($row['Ref'] ?? null));

        if (! $sender) {
            return response()->json(['error' => 'Нова пошта не повернула відправника для цього ключа.'], 422);
        }

        $contactsResponse = $novaPoshta->findCounterpartyContacts($sender['Ref']);
        $contactsError = $this->providerError($contactsResponse);

        if ($contactsError) {
            return response()->json(['error' => $contactsError], 422);
        }

        $contact = collect($contactsResponse['data'] ?? [])
            ->first(fn (array $row): bool => filled($row['Ref'] ?? null));

        $provider = ShippingProviderSetting::query()
            ->firstOrNew(['code' => 'nova_poshta']);
        $settings = $provider->exists ? ($provider->settings ?? []) : [];
        $settings = array_merge($settings, [
            'sender_ref' => $sender['Ref'],
            'sender_name' => $sender['Description'] ?? $sender['FirstName'] ?? $settings['sender_name'] ?? null,
            'contact_ref' => $contact['Ref'] ?? $settings['contact_ref'] ?? null,
            'contact_name' => $contact['Description'] ?? $contact['FirstName'] ?? $settings['contact_name'] ?? null,
            'sender_phone' => $contact['Phones'] ?? $contact['Phone'] ?? $settings['sender_phone'] ?? null,
        ]);

        $provider->fill([
            'name' => 'Нова пошта',
            'settings' => $settings,
            'is_active' => $provider->exists ? $provider->is_active : true,
            'sort_order' => 10,
        ])->save();

        app()->forgetInstance(NovaPoshtaApi::class);

        return response()->json([
            'message' => 'Дані відправника синхронізовано',
            'sender_ref' => $settings['sender_ref'],
            'sender_name' => $settings['sender_name'] ?? null,
            'contact_ref' => $settings['contact_ref'] ?? null,
            'contact_name' => $settings['contact_name'] ?? null,
            'sender_phone' => $settings['sender_phone'] ?? null,
        ]);
    }

    private function settings(): array
    {
        $provider = ShippingProviderSetting::query()
            ->where('code', 'nova_poshta')
            ->first();
        $settings = $provider?->settings ?? [];

        return [
            'is_active' => $provider?->is_active ?? true,
            'has_api_key' => filled($settings['api_key'] ?? config('services.nova_poshta.api_key')),
            'api_url' => $settings['api_url'] ?? config('services.nova_poshta.api_url', 'https://api.novaposhta.ua/v2.0/json/'),
            'sender_phone' => $settings['sender_phone'] ?? '',
            'sender_city_ref' => $settings['sender_city_ref'] ?? config('services.nova_poshta.sender_city_ref'),
            'sender_city_name' => $settings['sender_city_name'] ?? '',
            'sender_warehouse_ref' => $settings['sender_warehouse_ref'] ?? config('services.nova_poshta.sender_warehouse_ref'),
            'sender_warehouse_name' => $settings['sender_warehouse_name'] ?? '',
            'sender_ref' => $settings['sender_ref'] ?? '',
            'sender_name' => $settings['sender_name'] ?? '',
            'contact_ref' => $settings['contact_ref'] ?? '',
            'contact_name' => $settings['contact_name'] ?? '',
            'default_weight' => (string) ($settings['default_weight'] ?? config('services.nova_poshta.default_weight', 1)),
            'source' => $provider ? 'database' : 'env',
        ];
    }

    private function providerError(array $response): ?string
    {
        if (! array_key_exists('success', $response) || $response['success'] === true) {
            return null;
        }

        $messages = $response['errors'] ?? $response['warnings'] ?? null;

        if (is_string($messages)) {
            return trim($messages) !== '' ? trim($messages) : null;
        }

        if (! is_array($messages) || $messages === []) {
            return 'Нова пошта повернула помилку.';
        }

        $first = $messages[0] ?? null;

        if (is_array($first) && is_string($first['message'] ?? null) && trim($first['message']) !== '') {
            return trim($first['message']);
        }

        return is_string($first) && trim($first) !== ''
            ? trim($first)
            : 'Нова пошта повернула помилку.';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
