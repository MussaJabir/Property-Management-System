<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Models\PaymentRequest;
use App\Services\Payments\PaymentConfirmer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Selcom's payment callback for one workspace (the URL is sent with each
 * order). Selcom calls it on successful payments only.
 *
 * The callback is not signed, so its body is only used to find our order:
 * the payment is recorded after PaymentConfirmer asks Selcom for the
 * order's status with the workspace's own keys. A forged call can at most
 * make us check an order we already know about.
 */
class SelcomWebhookController
{
    public function __invoke(Request $request, PaymentConfirmer $confirmer): JsonResponse
    {
        $orderId = $request->input('order_id');

        $paymentRequest = is_string($orderId) && $orderId !== ''
            ? PaymentRequest::query()->where('order_id', $orderId)->first()
            : null;

        if ($paymentRequest === null) {
            Log::info('Selcom webhook for unknown order', ['tenant' => tenant('id'), 'order_id' => $orderId]);

            return response()->json(['result' => 'FAIL', 'message' => 'Unknown order'], 404);
        }

        $outcome = $confirmer->confirm($paymentRequest);

        return response()->json(['result' => 'SUCCESS', 'status' => $outcome]);
    }

    /** The callback address for the current workspace. */
    public static function url(): string
    {
        return route('selcom.webhook', ['tenant' => tenant('id')]);
    }
}
