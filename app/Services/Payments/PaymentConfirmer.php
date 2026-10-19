<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\SelcomUnavailable;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\SelcomAccount;
use App\Services\Payments\Selcom\SelcomClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Settles a pending payment request by asking Selcom for the order's
 * status — never by trusting what a webhook said, since Selcom's webhook
 * is not signed. COMPLETED records a Payment (which issues the receipt);
 * CANCELLED, USERCANCELLED and REJECTED close the request; anything else
 * leaves it pending for the next check.
 *
 * Safe to call more than once and from several places at the same time
 * (webhook, "Check payment" button, scheduled checks): the request row is
 * locked and only a pending request is ever settled.
 *
 * Callers must run inside the request's tenancy.
 */
class PaymentConfirmer
{
    public const OUTCOME_PAID = 'paid';

    public const OUTCOME_CANCELLED = 'cancelled';

    public const OUTCOME_PENDING = 'pending';

    /** Already settled before this call. */
    public const OUTCOME_DONE = 'done';

    /** Selcom statuses that mean the payer did not pay. */
    private const NOT_PAID = ['CANCELLED', 'USERCANCELLED', 'REJECTED'];

    /** @return self::OUTCOME_* */
    public function confirm(PaymentRequest $request): string
    {
        if (! $request->isPending()) {
            return self::OUTCOME_DONE;
        }

        $client = tenant();
        $account = $client instanceof Client ? SelcomAccount::forClient($client) : null;

        try {
            if ($account === null) {
                throw SelcomUnavailable::notConfigured();
            }

            $response = SelcomClient::for($account)->orderStatus($request->order_id);
        } catch (SelcomUnavailable $e) {
            Log::warning('Selcom order-status unavailable', ['order_id' => $request->order_id, 'error' => $e->getMessage()]);
            $this->touch($request);

            return self::OUTCOME_PENDING;
        }

        $order = $response->first();
        $status = strtoupper((string) ($order['payment_status'] ?? ''));

        if (! $response->successful() || ($order['order_id'] ?? $request->order_id) !== $request->order_id) {
            $this->touch($request);

            return self::OUTCOME_PENDING;
        }

        if ($status === 'COMPLETED') {
            return $this->settle($request, fn (PaymentRequest $locked) => $this->recordPayment($locked, $order))
                ? self::OUTCOME_PAID
                : self::OUTCOME_DONE;
        }

        if (in_array($status, self::NOT_PAID, true)) {
            return $this->settle($request, function (PaymentRequest $locked) use ($status): void {
                $locked->forceFill([
                    'status' => PaymentRequest::STATUS_CANCELLED,
                    'failure_reason' => $status === 'REJECTED' ? __('Rejected by the mobile network') : __('Cancelled by the payer'),
                    'last_checked_at' => now(),
                ])->save();
            }) ? self::OUTCOME_CANCELLED : self::OUTCOME_DONE;
        }

        // PENDING, INPROGRESS, or something new: ask again later.
        $this->touch($request);

        return self::OUTCOME_PENDING;
    }

    /**
     * Run $apply on the request under a row lock, only if it is still
     * pending. Returns whether it ran.
     *
     * @param  callable(PaymentRequest): void  $apply
     */
    private function settle(PaymentRequest $request, callable $apply): bool
    {
        $settled = DB::transaction(function () use ($request, $apply): bool {
            $locked = PaymentRequest::query()->whereKey($request->getKey())->lockForUpdate()->first();

            if ($locked === null || ! $locked->isPending()) {
                return false;
            }

            $apply($locked);

            return true;
        });

        $request->refresh();

        return $settled;
    }

    /**
     * Record what Selcom says was paid. The amount comes from Selcom, not
     * from our request, so the books match the money that arrived.
     *
     * @param  array<string, mixed>  $order
     */
    private function recordPayment(PaymentRequest $request, array $order): void
    {
        $shillings = isset($order['amount']) && is_numeric($order['amount'])
            ? (int) round((float) $order['amount'])
            : intdiv($request->amount, 100);

        $channel = isset($order['channel']) ? (string) $order['channel'] : null;
        $transid = isset($order['transid']) ? (string) $order['transid'] : null;
        $reference = isset($order['reference']) ? (string) $order['reference'] : null;

        if ($shillings * 100 !== $request->amount) {
            Log::warning('Selcom paid amount differs from request', [
                'order_id' => $request->order_id, 'requested' => $request->amount, 'paid' => $shillings * 100,
            ]);
        }

        $payment = Payment::create([
            'invoice_id' => $request->invoice_id,
            'amount' => $shillings * 100,
            'currency' => $request->currency,
            'payment_date' => today()->toDateString(),
            'method' => Payment::METHOD_MOBILE_MONEY,
            'mobile_money_provider' => self::provider($channel),
            'transaction_id' => $transid,
            'reference_number' => $reference,
            'received_by_user_id' => null,
            'status' => Payment::STATUS_COMPLETED,
            'notes' => __('Paid through Selcom from +:phone (order :order).', ['phone' => $request->msisdn, 'order' => $request->order_id]),
        ]);

        $request->forceFill([
            'status' => PaymentRequest::STATUS_COMPLETED,
            'payment_id' => $payment->id,
            'channel' => $channel,
            'selcom_transid' => $transid,
            'selcom_reference' => $reference,
            'completed_at' => now(),
            'last_checked_at' => now(),
        ])->save();
    }

    /**
     * Our network key for Selcom's channel name, e.g. TIGOPESATZ → tigopesa.
     * Unknown channels are kept on the request but leave this empty.
     */
    public static function provider(?string $channel): ?string
    {
        $channel = strtoupper((string) $channel);

        return match (true) {
            $channel === '' => null,
            str_contains($channel, 'MPESA') || str_contains($channel, 'VODA') => Payment::PROVIDER_MPESA,
            str_contains($channel, 'TIGO') || str_contains($channel, 'MIXX') || str_contains($channel, 'YAS') => Payment::PROVIDER_TIGOPESA,
            str_contains($channel, 'AIRTEL') => Payment::PROVIDER_AIRTELMONEY,
            str_contains($channel, 'HALO') => Payment::PROVIDER_HALOPESA,
            default => null,
        };
    }

    private function touch(PaymentRequest $request): void
    {
        $request->forceFill(['last_checked_at' => now()])->save();
    }
}
