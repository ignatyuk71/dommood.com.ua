<?php

namespace App\Services\Marketing\Clients;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TikTokEventsApiClient
{
    private const ENDPOINT = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    public function send(string $pixelId, string $accessToken, array $eventPayload, ?string $testEventCode = null): array
    {
        $body = [
            'event_source' => 'web',
            'event_source_id' => $pixelId,
            'data' => [$eventPayload],
        ];

        if ($testEventCode !== null && $testEventCode !== '') {
            $body['test_event_code'] = $testEventCode;
        }

        $response = Http::withHeaders([
            'Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])
            ->asJson()
            ->timeout(10)
            ->retry(2, 300)
            ->post(self::ENDPOINT, $body);

        $json = $response->json();

        if (! $response->successful()) {
            throw new RuntimeException(is_array($json)
                ? (string) (data_get($json, 'message') ?: data_get($json, 'error.message') ?: 'TikTok Events API HTTP error')
                : 'TikTok Events API HTTP error');
        }

        if (is_array($json) && isset($json['code']) && (int) $json['code'] !== 0) {
            throw new RuntimeException((string) ($json['message'] ?? 'TikTok Events API response error'));
        }

        if (is_array($json) && isset($json['error'])) {
            throw new RuntimeException((string) data_get($json, 'error.message', 'TikTok Events API response error'));
        }

        return is_array($json) ? $json : [];
    }
}
