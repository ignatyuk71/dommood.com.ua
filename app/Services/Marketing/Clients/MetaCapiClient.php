<?php

namespace App\Services\Marketing\Clients;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaCapiClient
{
    private const GRAPH_VERSION = 'v22.0';

    public function send(string $pixelId, string $accessToken, array $eventPayload, ?string $testEventCode = null): array
    {
        $endpoint = sprintf(
            'https://graph.facebook.com/%s/%s/events',
            self::GRAPH_VERSION,
            rawurlencode($pixelId)
        );

        $body = ['data' => [$eventPayload]];

        if ($testEventCode !== null && $testEventCode !== '') {
            $body['test_event_code'] = $testEventCode;
        }

        $response = Http::asJson()
            ->timeout(10)
            ->retry(2, 300)
            ->post($endpoint.'?access_token='.urlencode($accessToken), $body);

        $json = $response->json();

        if (! $response->successful()) {
            throw new RuntimeException(is_array($json) ? (string) data_get($json, 'error.message', 'Meta CAPI HTTP error') : 'Meta CAPI HTTP error');
        }

        if (is_array($json) && isset($json['error'])) {
            throw new RuntimeException((string) data_get($json, 'error.message', 'Meta CAPI response error'));
        }

        return is_array($json) ? $json : [];
    }
}
