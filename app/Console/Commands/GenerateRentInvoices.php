<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\RentInvoiceGenerator;
use Illuminate\Console\Command;
use Stancl\Tenancy\Facades\Tenancy;
use Throwable;

/**
 * Issues the current period's rent invoice for every active lease, in every
 * workspace that has automatic invoices on (Settings → Preferences).
 *
 * Runs daily before overdue detection (see routes/console.php). Safe to run
 * again: a period that already has an invoice is skipped.
 */
class GenerateRentInvoices extends Command
{
    protected $signature = 'billing:generate-rent-invoices {--client= : Only run for a single client slug or UUID}';

    protected $description = 'Issue this period\'s rent invoices for active leases (daily scheduler).';

    public function handle(RentInvoiceGenerator $generator): int
    {
        $clientsQuery = Client::query()->where('status', 'active');

        if ($slugOrId = $this->option('client')) {
            $clientsQuery->where(function ($q) use ($slugOrId) {
                $q->where('slug', $slugOrId)->orWhere('id', $slugOrId);
            });
        }

        $total = 0;

        foreach ($clientsQuery->cursor() as $client) {
            if (! RentInvoiceGenerator::enabledFor($client)) {
                continue;
            }

            try {
                Tenancy::initialize($client);
                $created = $generator->generateForWorkspace();
                Tenancy::end();

                if ($created > 0) {
                    $this->info("[{$client->slug}] issued {$created} rent invoice(s)");
                }

                $total += $created;
            } catch (Throwable $e) {
                Tenancy::end();
                $this->error("[{$client->slug}] failed: {$e->getMessage()}");
            }
        }

        $this->line("Done. Issued {$total} rent invoice(s).");

        return self::SUCCESS;
    }
}
