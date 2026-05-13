<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payments\LiqPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class LiqPayController extends Controller
{
    public function callback(Request $request, LiqPayService $liqPay): Response
    {
        $data = trim((string) $request->input('data'));
        $signature = trim((string) $request->input('signature'));

        if ($data === '' || $signature === '') {
            return response('missing data or signature', 422);
        }

        try {
            $liqPay->handleCallback($data, $signature);
        } catch (ValidationException $exception) {
            report($exception);

            return response('invalid signature', 403);
        }

        return response('ok');
    }

    public function result(Request $request, LiqPayService $liqPay): Response|RedirectResponse
    {
        $data = trim((string) $request->input('data'));
        $payload = [];

        if ($data !== '') {
            try {
                $payload = $liqPay->decodePayload($data);
            } catch (ValidationException) {
                $payload = [];
            }
        }

        $orderNumber = trim((string) ($payload['order_id'] ?? $request->query('order')));
        $status = trim((string) ($payload['status'] ?? $request->query('status', 'processing')));

        return response()->view('payments.liqpay-result', [
            'orderNumber' => $orderNumber,
            'status' => $status,
        ]);
    }
}
