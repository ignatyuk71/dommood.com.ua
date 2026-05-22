<?php

namespace App\Services\Marketing\Clients;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAdsApiClient
{
    private const ADS_API_VERSION = 'v20';

    private const OAUTH_SCOPE = 'https://www.googleapis.com/auth/adwords';

    private const DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    public function uploadClickConversion(array $config, array $conversion): array
    {
        $customerId = $this->digits((string) ($config['ads_api_customer_id'] ?? ''));
        $developerToken = trim((string) ($config['ads_developer_token'] ?? ''));
        $serviceAccountJson = trim((string) ($config['ads_service_account_json'] ?? ''));

        if ($customerId === '' || $developerToken === '' || $serviceAccountJson === '') {
            throw new RuntimeException('Google Ads API не налаштовано: бракує customer ID, developer token або service account JSON.');
        }

        $headers = ['developer-token' => $developerToken];
        $loginCustomerId = $this->digits((string) ($config['ads_api_login_customer_id'] ?? ''));

        if ($loginCustomerId !== '') {
            $headers['login-customer-id'] = $loginCustomerId;
        }

        $response = Http::asJson()
            ->withToken($this->accessToken($serviceAccountJson))
            ->withHeaders($headers)
            ->post(sprintf('https://googleads.googleapis.com/%s/customers/%s:uploadClickConversions', self::ADS_API_VERSION, $customerId), [
                'conversions' => [$conversion],
                'partialFailure' => true,
                'validateOnly' => ($config['mode'] ?? 'prod') === 'test',
            ]);

        $response->throw();

        $body = $response->json();
        $partialFailure = is_array($body['partialFailureError'] ?? null) ? $body['partialFailureError'] : null;

        if ($partialFailure && $this->hasPartialFailure($partialFailure)) {
            throw new RuntimeException($this->formatPartialFailure($partialFailure));
        }

        return [
            'request_id' => trim((string) $response->header('request-id', '')),
            'body' => is_array($body) ? $body : [],
        ];
    }

    private function accessToken(string $serviceAccountJson): string
    {
        $cacheKey = 'google-ads-token:'.hash('sha256', $serviceAccountJson);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $serviceAccount = json_decode($serviceAccountJson, true);
        if (! is_array($serviceAccount)) {
            throw new RuntimeException('Service account JSON для Google Ads має невалідний формат.');
        }

        $clientEmail = trim((string) ($serviceAccount['client_email'] ?? ''));
        $privateKey = trim((string) ($serviceAccount['private_key'] ?? ''));
        $tokenUri = trim((string) ($serviceAccount['token_uri'] ?? self::DEFAULT_TOKEN_URI));

        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Service account JSON для Google Ads не містить client_email або private_key.');
        }

        $response = Http::asForm()->post($tokenUri, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->jwt($clientEmail, $privateKey, $tokenUri),
        ]);

        $response->throw();

        $payload = $response->json();
        $accessToken = trim((string) ($payload['access_token'] ?? ''));
        $expiresIn = max(60, (int) ($payload['expires_in'] ?? 3600));

        if ($accessToken === '') {
            throw new RuntimeException('Google OAuth не повернув access_token для Google Ads API.');
        }

        Cache::put($cacheKey, $accessToken, now()->addSeconds(max(60, $expiresIn - 120)));

        return $accessToken;
    }

    private function jwt(string $clientEmail, string $privateKey, string $tokenUri): string
    {
        $now = now()->timestamp;
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES) ?: '{}');
        $claims = $this->base64Url(json_encode([
            'iss' => $clientEmail,
            'scope' => self::OAUTH_SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_UNESCAPED_SLASHES) ?: '{}');

        $unsigned = $header.'.'.$claims;
        $signature = '';

        if (openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256) !== true || $signature === '') {
            throw new RuntimeException('Не вдалося підписати JWT для Google Ads service account.');
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    private function hasPartialFailure(array $partialFailure): bool
    {
        return trim((string) ($partialFailure['message'] ?? '')) !== ''
            || (is_array($partialFailure['details'] ?? null) && $partialFailure['details'] !== []);
    }

    private function formatPartialFailure(array $partialFailure): string
    {
        $message = trim((string) ($partialFailure['message'] ?? ''));

        return $message !== ''
            ? 'Google Ads API partial failure: '.$message
            : 'Google Ads API partial failure: '.json_encode($partialFailure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
