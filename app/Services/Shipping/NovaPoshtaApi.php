<?php

namespace App\Services\Shipping;

use Illuminate\Support\Facades\Http;

class NovaPoshtaApi
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $apiUrl = 'https://api.novaposhta.ua/v2.0/json/',
        private readonly ?string $senderCityRef = null,
        private readonly ?string $senderWarehouseRef = null,
        private readonly float $defaultWeight = 1.0,
        private readonly bool $active = true,
    ) {
    }

    public function configured(): bool
    {
        return $this->active && trim((string) $this->apiKey) !== '' && trim($this->apiUrl) !== '';
    }

    public function senderCityRef(): ?string
    {
        return $this->senderCityRef;
    }

    public function senderWarehouseRef(): ?string
    {
        return $this->senderWarehouseRef;
    }

    public function findCities(string $query, int $limit = 20, int $page = 1): array
    {
        return $this->request('Address', 'getCities', [
            'FindByString' => $query,
            'Limit' => $limit,
            'Page' => $page,
        ]);
    }

    public function findWarehouses(string $cityRef, string $query = '', int $limit = 20, int $page = 1): array
    {
        return $this->request('Address', 'getWarehouses', [
            'CityRef' => $cityRef,
            'FindByString' => $query,
            'Limit' => $limit,
            'Page' => $page,
        ]);
    }

    public function calculatePrice(array $payload): array
    {
        return $this->request('InternetDocument', 'getDocumentPrice', [
            'CitySender' => $payload['sender_city_ref'] ?? null,
            'CityRecipient' => $payload['recipient_city_ref'] ?? null,
            'Weight' => $payload['weight'] ?? $this->defaultWeight,
            'ServiceType' => $payload['service_type'] ?? 'WarehouseWarehouse',
            'Cost' => $payload['cost'] ?? 0,
            'CargoType' => $payload['cargo_type'] ?? 'Parcel',
            'SeatsAmount' => $payload['seats_amount'] ?? 1,
        ]);
    }

    public function findSenderCounterparties(int $page = 1): array
    {
        return $this->request('Counterparty', 'getCounterparties', [
            'CounterpartyProperty' => 'Sender',
            'Page' => $page,
        ]);
    }

    public function findCounterpartyContacts(string $counterpartyRef, int $page = 1): array
    {
        return $this->request('Counterparty', 'getCounterpartyContactPersons', [
            'Ref' => $counterpartyRef,
            'Page' => $page,
        ]);
    }

    private function request(string $modelName, string $calledMethod, array $methodProperties = []): array
    {
        if (! $this->configured()) {
            return [
                'success' => false,
                'data' => [],
                'errors' => ['Не налаштовано API ключ Нової пошти.'],
            ];
        }

        try {
            $response = Http::retry(2, 200)
                ->connectTimeout(10)
                ->timeout(20)
                ->withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ])
                ->post($this->apiUrl, [
                    'apiKey' => $this->apiKey,
                    'modelName' => $modelName,
                    'calledMethod' => $calledMethod,
                    // Нова пошта стабільніше приймає числові значення як рядки.
                    'methodProperties' => $this->normalizeProperties($methodProperties),
                ]);

            return $response->json() ?? [];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'success' => false,
                'data' => [],
                'errors' => ['Сервіс Нової пошти тимчасово недоступний.'],
            ];
        }
    }

    private function normalizeProperties(array $properties): array
    {
        $normalized = [];

        foreach ($properties as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_int($value) || is_float($value)) {
                $normalized[$key] = (string) $value;
                continue;
            }

            $normalized[$key] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }
}
