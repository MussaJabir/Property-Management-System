<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant deposits, kept apart from rent: what came in, what was deducted
 * (with a reason), what was refunded, and what moved to a renewal. The amount
 * held on a lease is the sum of these, never rental income.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('lease_id')->constrained('leases')->cascadeOnDelete();
            // received | deduction | refund | carried_in | carried_out
            $table->string('type', 20);
            $table->unsignedBigInteger('amount'); // minor units, always positive
            $table->date('occurred_on');
            $table->string('reason', 500)->nullable();
            // A deposit received through an invoice points at it (one row per invoice).
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'lease_id']);
            $table->unique(['invoice_id', 'type']);
        });

        // Deposits already paid: every fully paid invoice's deposit lines.
        DB::statement(<<<'SQL'
            INSERT INTO deposit_transactions (tenant_id, lease_id, type, amount, occurred_on, invoice_id, created_at, updated_at)
            SELECT i.tenant_id, i.lease_id, 'received', SUM(it.line_total),
                   COALESCE(i.paid_at::date, i.issued_at::date, CURRENT_DATE), i.id, NOW(), NOW()
            FROM invoices i
            JOIN invoice_items it ON it.invoice_id = i.id AND it.type = 'deposit'
            WHERE i.status = 'paid' AND i.lease_id IS NOT NULL AND i.deleted_at IS NULL
            GROUP BY i.tenant_id, i.lease_id, i.id, i.paid_at, i.issued_at
            HAVING SUM(it.line_total) > 0
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_transactions');
    }
};
