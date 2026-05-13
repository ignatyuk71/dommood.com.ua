<?php

namespace App\Http\Controllers;

use App\Services\Shipping\NovaPoshtaApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ShippingLookupController extends Controller
{
    private const CACHE_TTL_SECONDS = 10800;

    public function __construct(
        private readonly NovaPoshtaApi $novaPoshta,
    ) {
    }

    public function novaPoshtaCities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = trim($data['q']);
        $page = (int) ($data['page'] ?? 1);
        $limit = min(50, max(1, (int) ($data['limit'] ?? 20)));

        if ($query === '') {
            return response()->json([
                'items' => [],
                'error' => null,
            ]);
        }

        if (! $this->novaPoshta->configured()) {
            return response()->json([
                'items' => [],
                'error' => 'Не налаштовано API ключ Нової пошти.',
            ], 422);
        }

        $cacheKey = $this->cacheKey('np:cities', compact('query', 'page', 'limit'));
        if (Cache::has($cacheKey)) {
            return response()->json([
                'items' => Cache::get($cacheKey, []),
                'error' => null,
            ]);
        }

        $response = $this->novaPoshta->findCities($query, $limit, $page);
        $error = $this->providerError($response);
        $cities = $error ? [] : collect($response['data'] ?? [])
            ->map(fn (array $row): array => [
                'ref' => $row['Ref'] ?? null,
                'name' => $row['Description'] ?? $row['DescriptionUa'] ?? $row['DescriptionRu'] ?? '',
                'region' => $row['RegionDescription'] ?? $row['RegionDescriptionUa'] ?? $row['RegionDescriptionRu'] ?? null,
                'area' => $row['AreaDescription'] ?? $row['AreaDescriptionUa'] ?? $row['AreaDescriptionRu'] ?? null,
            ])
            ->filter(fn (array $row): bool => filled($row['ref']) && filled($row['name']))
            ->values()
            ->all();

        if (! $error) {
            Cache::put($cacheKey, $cities, now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return response()->json([
            'items' => $cities,
            'error' => $error,
        ]);
    }

    public function novaPoshtaWarehouses(Request $request): JsonResponse
    {
        $data = $request->validate([
            'city_ref' => ['required', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:branch,postomat'],
            'page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $cityRef = trim($data['city_ref']);
        $query = trim($data['q'] ?? '');
        $type = $data['type'] ?? null;
        $page = (int) ($data['page'] ?? 1);
        $limit = min(50, max(1, (int) ($data['limit'] ?? 20)));
        $cacheKey = $this->cacheKey('np:warehouses', compact('cityRef', 'query', 'type', 'page', 'limit'));

        if (! $this->novaPoshta->configured()) {
            return response()->json([
                'items' => [],
                'error' => 'Не налаштовано API ключ Нової пошти.',
            ], 422);
        }

        if (Cache::has($cacheKey)) {
            return response()->json([
                'items' => Cache::get($cacheKey, []),
                'error' => null,
            ]);
        }

        $response = $this->novaPoshta->findWarehouses($cityRef, $query, $limit, $page);
        $error = $this->providerError($response);
        $warehouses = $error ? [] : collect($response['data'] ?? [])
            ->map(fn (array $row): array => [
                'ref' => $row['Ref'] ?? null,
                'name' => $row['Description'] ?? $row['DescriptionUa'] ?? $row['DescriptionRu'] ?? '',
                'type' => $this->warehouseType($row),
                'type_ref' => $row['TypeOfWarehouse'] ?? null,
                'address' => $row['ShortAddress'] ?? $row['ShortAddressUa'] ?? $row['ShortAddressRu'] ?? null,
                'number' => $row['Number'] ?? null,
            ])
            ->filter(fn (array $row): bool => filled($row['ref']) && filled($row['name']))
            ->when($type, fn ($items) => $items->where('type', $type))
            ->values()
            ->all();

        if (! $error) {
            Cache::put($cacheKey, $warehouses, now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return response()->json([
            'items' => $warehouses,
            'error' => $error,
        ]);
    }

    public function novaPoshtaPrice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient_city_ref' => ['required', 'string', 'max:100'],
            'sender_city_ref' => ['nullable', 'string', 'max:100'],
            'service_type' => ['nullable', 'string', 'in:WarehouseWarehouse,WarehouseDoors,DoorsWarehouse,DoorsDoors'],
            'weight' => ['nullable', 'numeric', 'min:0.1', 'max:1000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'seats_amount' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $senderCityRef = $data['sender_city_ref']
            ?? $this->novaPoshta->senderCityRef()
            ?? config('services.nova_poshta.sender_city_ref');

        if (! filled($senderCityRef)) {
            return response()->json([
                'price' => null,
                'price_cents' => null,
                'currency' => 'UAH',
                'error' => 'Не налаштовано місто відправника Нової пошти.',
            ], 422);
        }

        $response = $this->novaPoshta->calculatePrice([
            ...$data,
            'sender_city_ref' => $senderCityRef,
        ]);

        $error = $this->providerError($response);
        if ($error) {
            return response()->json([
                'price' => null,
                'price_cents' => null,
                'currency' => 'UAH',
                'error' => $error,
            ], 422);
        }

        $row = $response['data'][0] ?? [];
        $price = (float) ($row['Cost'] ?? $row['cost'] ?? 0);

        return response()->json([
            'price' => $price,
            'price_cents' => (int) round($price * 100),
            'currency' => 'UAH',
            'raw' => $row,
            'error' => null,
        ]);
    }

    private function providerError(array $response): ?string
    {
        if (! array_key_exists('success', $response) || $response['success'] === true) {
            return null;
        }

        return $this->firstProviderMessage($response['errors'] ?? null)
            ?? $this->firstProviderMessage($response['warnings'] ?? null)
            ?? 'Нова пошта повернула помилку.';
    }

    private function firstProviderMessage(mixed $messages): ?string
    {
        if (is_string($messages)) {
            return trim($messages) !== '' ? trim($messages) : null;
        }

        if (! is_array($messages) || $messages === []) {
            return null;
        }

        $first = $messages[0] ?? null;

        if (is_string($first)) {
            return trim($first) !== '' ? trim($first) : null;
        }

        return is_array($first) && is_string($first['message'] ?? null)
            ? trim($first['message'])
            : null;
    }

    private function warehouseType(array $row): string
    {
        $description = mb_strtolower((string) ($row['Description'] ?? $row['DescriptionUa'] ?? $row['DescriptionRu'] ?? ''));
        $category = mb_strtolower((string) ($row['CategoryOfWarehouse'] ?? ''));

        return str_contains($description, 'поштомат') || str_contains($category, 'postomat')
            ? 'postomat'
            : 'branch';
    }

    private function cacheKey(string $prefix, array $payload): string
    {
        return 'shipping_lookup:'.$prefix.':'.md5(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
