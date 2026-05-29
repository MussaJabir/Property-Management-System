<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Base class for per-tenant, per-year monotonic document number generation
 * (invoices, receipts, future quotes/credit-notes). Concurrency-safe via a
 * Postgres advisory transaction-scoped lock keyed by (year, hash(tenant_id)).
 *
 * Subclasses declare the backing table and the prefix string; the rest is
 * shared.
 */
abstract class DocumentNumberGenerator
{
    /** The sequences table name (e.g. `invoice_sequences`). */
    abstract protected function table(): string;

    /** The number prefix (e.g. `INV`, `RCP`). */
    abstract protected function prefix(): string;

    public function next(string $tenantId, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $table = $this->table();

        return DB::transaction(function () use ($tenantId, $year, $table): string {
            // Serialize concurrent next() calls for the same (tenant, year).
            // pg_advisory_xact_lock auto-releases at COMMIT / ROLLBACK.
            DB::statement('SELECT pg_advisory_xact_lock(?, ?)', [
                $year,
                $this->hashTenant($tenantId),
            ]);

            $row = DB::table($table)
                ->where('tenant_id', $tenantId)
                ->where('year', $year)
                ->first();

            if ($row === null) {
                $next = 1;
                DB::table($table)->insert([
                    'tenant_id' => $tenantId,
                    'year' => $year,
                    'last_number' => $next,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $next = ((int) $row->last_number) + 1;
                DB::table($table)
                    ->where('tenant_id', $tenantId)
                    ->where('year', $year)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            }

            return $this->format($tenantId, $year, $next);
        });
    }

    protected function format(string $tenantId, int $year, int $number): string
    {
        $client = Client::find($tenantId);
        if (! $client) {
            throw new RuntimeException("Client {$tenantId} not found when issuing document number");
        }

        return sprintf('%s-%s-%d-%06d', $this->prefix(), strtoupper($client->slug), $year, $number);
    }

    /** 31-bit positive int so it fits Postgres advisory-lock key. */
    protected function hashTenant(string $tenantId): int
    {
        return (int) (crc32($tenantId) & 0x7FFFFFFF);
    }
}
