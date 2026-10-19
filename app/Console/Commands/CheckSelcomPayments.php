<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\PaymentRequest;
use App\Services\Payments\PaymentConfirmer;
use App\Services\Payments\PaymentRequester;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Facades\Tenancy;
use Throwable;

/**
 * Asks Selcom about mobile money prompts that are still waiting, so a
 * payment is recorded even when Selcom's callback never arrives.
 *
 * Follows Selcom's guidance for unresolved transactions: wait 3 minutes,
 * then query every 3 minutes. Once the order has expired (plus a margin)
 * an unpaid prompt is marked expired; expired ones are still looked at
 * every 30 minutes for a day, so a late payment is not lost. Anything
 * still unresolved after that is logged for follow-up with Selcom
 * (helpdesk@selcom.net).
 *
 * Runs every 3 minutes (routes/console.php), in every workspace with
 * waiting prompts, including suspended ones: the money has moved.
 */
class CheckSelcomPayments extends Command
{
    /** Selcom: wait this long before the first query, and between queries. */
    public const INTERVAL_MINUTES = 3;

    /** Order expiry plus a margin for slow networks. */
    public const EXPIRE_AFTER_MINUTES = PaymentRequester::ORDER_EXPIRY_MINUTES + 15;

    /** How long, and how often, expired prompts are still looked at. */
    public const LATE_WINDOW_HOURS = 24;

    public const LATE_INTERVAL_MINUTES = 30;

    protected $signature = 'payments:check-selcom {--client= : Only run for a single client slug}';

    protected $description = 'Ask Selcom about waiting mobile money payments (every 3 minutes).';

    public function handle(PaymentConfirmer $confirmer): int
    {
        $clientIds = self::due(PaymentRequest::query()->withoutGlobalScopes())
            ->when($this->option('client'), fn (Builder $q, $slug) => $q->where('tenant_id', $slug))
            ->distinct()
            ->pluck('tenant_id');

        $paid = 0;

        foreach (Client::query()->whereIn('id', $clientIds)->cursor() as $client) {
            try {
                Tenancy::initialize($client);

                foreach (self::due(PaymentRequest::query())->oldest()->cursor() as $request) {
                    $outcome = $confirmer->confirm($request);

                    if ($outcome === PaymentConfirmer::OUTCOME_PAID) {
                        $paid++;
                        $this->info("[{$client->getKey()}] {$request->order_id} paid");
                    } elseif ($outcome === PaymentConfirmer::OUTCOME_PENDING) {
                        $this->settleStale($confirmer, $request->refresh());
                    }
                }

                Tenancy::end();
            } catch (Throwable $e) {
                Tenancy::end();
                $this->error("[{$client->getKey()}] failed: {$e->getMessage()}");
            }
        }

        $this->line("Done. {$paid} payment(s) recorded.");

        return self::SUCCESS;
    }

    /**
     * Requests worth asking Selcom about now.
     *
     * @param  Builder<PaymentRequest>  $query
     * @return Builder<PaymentRequest>
     */
    public static function due(Builder $query): Builder
    {
        $interval = now()->subMinutes(self::INTERVAL_MINUTES);
        $late = now()->subMinutes(self::LATE_INTERVAL_MINUTES);

        return $query->where(function (Builder $q) use ($interval, $late): void {
            $q->where(function (Builder $pending) use ($interval): void {
                $pending->where('status', PaymentRequest::STATUS_PENDING)
                    ->where('created_at', '<=', $interval)
                    ->where(fn (Builder $c) => $c->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', $interval));
            })->orWhere(function (Builder $expired) use ($late): void {
                $expired->where('status', PaymentRequest::STATUS_EXPIRED)
                    ->where('created_at', '>=', now()->subHours(self::LATE_WINDOW_HOURS))
                    ->where(fn (Builder $c) => $c->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', $late));
            });
        });
    }

    /** Expire an unpaid prompt past its order's life; flag one stuck for a day. */
    private function settleStale(PaymentConfirmer $confirmer, PaymentRequest $request): void
    {
        $age = $request->created_at?->diffInMinutes(now()) ?? 0;

        if ($request->isPending() && $age >= self::EXPIRE_AFTER_MINUTES) {
            $confirmer->expire($request);

            return;
        }

        if ($request->isExpired() && $age >= (self::LATE_WINDOW_HOURS * 60) - self::LATE_INTERVAL_MINUTES) {
            Log::warning('Selcom order unresolved after a day; check with helpdesk@selcom.net', [
                'tenant' => tenant('id'), 'order_id' => $request->order_id, 'transid' => $request->transid,
            ]);
        }
    }
}
