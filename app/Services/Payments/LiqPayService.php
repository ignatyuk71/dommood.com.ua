<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Services\SiteSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LiqPayService
{
    private const PAID_STATUSES = ['success', 'sandbox'];

    private const FAILED_STATUSES = ['failure', 'error', 'declined'];

    private const REFUNDED_STATUSES = ['reversed'];

    public function __construct(private readonly SiteSettingsService $settings) {}

    public function checkoutPayload(Order $order, ?PaymentMethod $method = null): array
    {
        $method ??= $this->activeMethod();
        $settings = $this->liqPaySettings($method);
        $publicKey = trim((string) ($settings['public_key'] ?? ''));

        if (! ($settings['enabled'] ?? false)) {
            throw ValidationException::withMessages([
                'liqpay' => 'Провайдер LiqPay вимкнений у налаштуваннях платежів.',
            ]);
        }

        if ($publicKey === '') {
            throw ValidationException::withMessages([
                'liqpay' => 'Для LiqPay не задано public key.',
            ]);
        }

        $payload = [
            'version' => 3,
            'public_key' => $publicKey,
            'action' => $settings['action'] ?? 'pay',
            'amount' => number_format(((int) $order->total_cents) / 100, 2, '.', ''),
            'currency' => $order->currency ?: 'UAH',
            'description' => 'Замовлення #'.$order->order_number,
            'order_id' => $order->order_number,
            'server_url' => $settings['server_url'] ?? route('payments.liqpay.callback'),
            'result_url' => $settings['result_url'] ?? route('payments.liqpay.result'),
            'language' => $settings['language'] ?? 'uk',
        ];

        if (($settings['mode'] ?? 'test') === 'test') {
            $payload['sandbox'] = 1;
        }

        $data = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        PaymentTransaction::query()->updateOrCreate(
            [
                'provider' => PaymentTransaction::PROVIDER_LIQPAY,
                'external_order_id' => $order->order_number,
            ],
            [
                'order_id' => $order->id,
                'payment_method' => $order->payment_method ?: 'liqpay',
                'action' => $payload['action'],
                'status' => PaymentTransaction::STATUS_PENDING,
                'amount_cents' => (int) $order->total_cents,
                'currency' => $payload['currency'],
                'is_test' => isset($payload['sandbox']),
                'request_payload' => $payload,
            ],
        );

        return [
            'data' => $data,
            'signature' => $this->signature($data, $this->privateKey($settings)),
            'payload' => $payload,
        ];
    }

    public function handleCallback(string $data, string $signature): PaymentTransaction
    {
        $privateKey = $this->privateKey($this->liqPaySettings($this->legacyMethod()));

        if (! hash_equals($this->signature($data, $privateKey), $signature)) {
            throw ValidationException::withMessages([
                'signature' => 'Невірний підпис LiqPay callback.',
            ]);
        }

        $payload = $this->decodePayload($data);

        return DB::transaction(function () use ($payload, $data, $signature): PaymentTransaction {
            $externalOrderId = $this->stringValue($payload, 'order_id');
            $providerTransactionId = $this->providerTransactionId($payload);
            $amountCents = $this->amountToCents($payload['amount'] ?? 0);
            $currency = $this->stringValue($payload, 'currency') ?: 'UAH';
            $order = $this->resolveOrder($externalOrderId);
            $status = $this->mapStatus($this->stringValue($payload, 'status'));
            $failureReason = null;

            if ($order && $amountCents !== (int) $order->total_cents) {
                $status = PaymentTransaction::STATUS_AMOUNT_MISMATCH;
                $failureReason = 'Сума callback не збігається із сумою замовлення.';
            }

            $transaction = $this->resolveTransaction($externalOrderId, $providerTransactionId);
            $transaction->fill([
                'order_id' => $order?->id,
                'provider' => PaymentTransaction::PROVIDER_LIQPAY,
                'external_order_id' => $externalOrderId,
                'provider_transaction_id' => $providerTransactionId,
                'payment_method' => $this->stringValue($payload, 'paytype') ?: 'liqpay',
                'action' => $this->stringValue($payload, 'action'),
                'status' => $status,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'is_test' => (bool) ($payload['sandbox'] ?? false),
                'callback_payload' => $payload,
                'raw_data' => $data,
                'raw_signature' => $signature,
                'failure_reason' => $failureReason,
                'processed_at' => now(),
                'paid_at' => $status === PaymentTransaction::STATUS_PAID ? now() : $transaction->paid_at,
            ]);
            $transaction->save();

            if ($order) {
                $this->syncOrderPaymentState($order, $transaction);
            }

            return $transaction->refresh();
        });
    }

    public function signature(string $data, string $privateKey): string
    {
        return base64_encode(sha1($privateKey.$data.$privateKey, true));
    }

    public function decodePayload(string $data): array
    {
        $json = base64_decode($data, true);

        if ($json === false) {
            throw ValidationException::withMessages([
                'data' => 'Некоректний base64 payload LiqPay.',
            ]);
        }

        $payload = json_decode($json, true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'data' => 'Некоректний JSON payload LiqPay.',
            ]);
        }

        return $payload;
    }

    private function activeMethod(): PaymentMethod
    {
        $method = PaymentMethod::query()
            ->where('type', 'liqpay')
            ->where('is_active', true)
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->first();

        if (! $method) {
            throw ValidationException::withMessages([
                'liqpay' => 'Активний метод LiqPay не налаштовано.',
            ]);
        }

        return $method;
    }

    private function legacyMethod(): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('type', 'liqpay')
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->first();
    }

    private function liqPaySettings(?PaymentMethod $method = null): array
    {
        $payments = $this->settings->get('payments');
        $legacyPublic = $method?->settings ?? [];
        $legacySecret = $method?->secret_settings ?? [];

        return [
            'enabled' => (bool) ($payments['liqpay_enabled'] ?? false),
            'mode' => $payments['liqpay_mode'] ?? $legacyPublic['mode'] ?? 'test',
            'public_key' => $payments['liqpay_public_key'] ?? $legacyPublic['public_key'] ?? '',
            'private_key' => $payments['liqpay_private_key'] ?? $legacySecret['private_key'] ?? '',
            'language' => $payments['liqpay_language'] ?? $legacyPublic['language'] ?? 'uk',
            'action' => $legacyPublic['action'] ?? 'pay',
            'server_url' => $payments['liqpay_server_url'] ?? $legacyPublic['server_url'] ?? route('payments.liqpay.callback'),
            'result_url' => $payments['liqpay_result_url'] ?? $legacyPublic['result_url'] ?? route('payments.liqpay.result'),
        ];
    }

    private function privateKey(array $settings): string
    {
        $privateKey = trim((string) ($settings['private_key'] ?? ''));

        if ($privateKey === '') {
            throw ValidationException::withMessages([
                'liqpay' => 'Для LiqPay не задано private key.',
            ]);
        }

        return $privateKey;
    }

    private function resolveOrder(?string $externalOrderId): ?Order
    {
        if (! $externalOrderId) {
            return null;
        }

        return Order::query()
            ->where('order_number', $externalOrderId)
            ->orWhere('id', ctype_digit($externalOrderId) ? (int) $externalOrderId : 0)
            ->first();
    }

    private function resolveTransaction(?string $externalOrderId, ?string $providerTransactionId): PaymentTransaction
    {
        if ($providerTransactionId) {
            return PaymentTransaction::query()
                ->where('provider', PaymentTransaction::PROVIDER_LIQPAY)
                ->where('provider_transaction_id', $providerTransactionId)
                ->firstOrNew([
                    'provider' => PaymentTransaction::PROVIDER_LIQPAY,
                    'provider_transaction_id' => $providerTransactionId,
                ]);
        }

        if (! $externalOrderId) {
            return new PaymentTransaction([
                'provider' => PaymentTransaction::PROVIDER_LIQPAY,
            ]);
        }

        return PaymentTransaction::query()
            ->where('provider', PaymentTransaction::PROVIDER_LIQPAY)
            ->where('external_order_id', $externalOrderId)
            ->firstOrNew([
                'provider' => PaymentTransaction::PROVIDER_LIQPAY,
                'external_order_id' => $externalOrderId,
            ]);
    }

    private function providerTransactionId(array $payload): ?string
    {
        return $this->stringValue($payload, 'payment_id')
            ?: $this->stringValue($payload, 'transaction_id')
            ?: $this->stringValue($payload, 'liqpay_order_id');
    }

    private function mapStatus(?string $status): string
    {
        $status = Str::lower(trim((string) $status));

        if (in_array($status, self::PAID_STATUSES, true)) {
            return PaymentTransaction::STATUS_PAID;
        }

        if (in_array($status, self::FAILED_STATUSES, true)) {
            return PaymentTransaction::STATUS_FAILED;
        }

        if (in_array($status, self::REFUNDED_STATUSES, true)) {
            return PaymentTransaction::STATUS_REFUNDED;
        }

        return PaymentTransaction::STATUS_PENDING;
    }

    private function syncOrderPaymentState(Order $order, PaymentTransaction $transaction): void
    {
        if ($transaction->status === PaymentTransaction::STATUS_PAID) {
            $order->forceFill([
                'payment_status' => 'paid',
                'payment_method' => $order->payment_method ?: 'liqpay',
                'payment_provider' => PaymentTransaction::PROVIDER_LIQPAY,
                'payment_reference' => $transaction->provider_transaction_id,
                'paid_at' => $order->paid_at ?: now(),
            ])->save();

            return;
        }

        if ($transaction->status === PaymentTransaction::STATUS_REFUNDED) {
            $order->forceFill([
                'payment_status' => 'refunded',
                'payment_provider' => PaymentTransaction::PROVIDER_LIQPAY,
                'payment_reference' => $transaction->provider_transaction_id ?: $order->payment_reference,
            ])->save();

            return;
        }

        if (
            in_array($transaction->status, [PaymentTransaction::STATUS_FAILED, PaymentTransaction::STATUS_AMOUNT_MISMATCH], true)
            && $order->payment_status !== 'paid'
        ) {
            $order->forceFill([
                'payment_status' => 'failed',
                'payment_provider' => PaymentTransaction::PROVIDER_LIQPAY,
                'payment_reference' => $transaction->provider_transaction_id ?: $order->payment_reference,
            ])->save();
        }
    }

    private function amountToCents(mixed $amount): int
    {
        return (int) round(((float) str_replace(',', '.', (string) $amount)) * 100);
    }

    private function stringValue(array $payload, string $key): ?string
    {
        $value = trim((string) ($payload[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
