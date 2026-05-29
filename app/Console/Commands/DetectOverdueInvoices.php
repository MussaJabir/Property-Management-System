<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Stancl\Tenancy\Facades\Tenancy;
use Throwable;

/**
 * Promotes unpaid / partial invoices past their due_date to `overdue`.
 *
 * Runs daily (see routes/console.php). Iterates every Client because the
 * Invoice global scope binds to the active tenant — there's no "all tenants"
 * query that doesn't break isolation. Per-tenant context is initialized then
 * torn down for each iteration so the next loop starts clean.
 */
class DetectOverdueInvoices extends Command
{
    protected $signature = 'billing:detect-overdue {--client= : Only run for a single client slug or UUID}';

    protected $description = 'Promote past-due unpaid/partial invoices to overdue (daily scheduler).';

    public function handle(): int
    {
        $clientsQuery = Client::query()->where('status', 'active');

        if ($slugOrId = $this->option('client')) {
            $clientsQuery->where(function ($q) use ($slugOrId) {
                $q->where('slug', $slugOrId)->orWhere('id', $slugOrId);
            });
        }

        $totalPromoted = 0;
        $totalClients = 0;

        foreach ($clientsQuery->cursor() as $client) {
            $totalClients++;
            $promoted = 0;

            try {
                Tenancy::initialize($client);

                Invoice::query()
                    ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
                    ->whereDate('due_date', '<', today())
                    ->cursor()
                    ->each(function (Invoice $invoice) use (&$promoted): void {
                        if ($invoice->markOverdueIfDue()) {
                            $promoted++;
                        }
                    });

                Tenancy::end();

                if ($promoted > 0) {
                    $this->info("[{$client->slug}] marked {$promoted} invoice(s) overdue");
                }

                $totalPromoted += $promoted;
            } catch (Throwable $e) {
                Tenancy::end();
                $this->error("[{$client->slug}] failed: {$e->getMessage()}");
            }
        }

        $this->line("Done. Checked {$totalClients} client(s), promoted {$totalPromoted} invoice(s).");

        return self::SUCCESS;
    }
}
