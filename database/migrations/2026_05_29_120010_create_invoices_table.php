<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice = a billable line item against a Lease for a billing period.
 *
 * Money columns are minor units (TZS cents).
 *
 * Status machine:
 *   draft   → issue()        → unpaid          (locks the number, makes it visible to renters)
 *   draft   → cancel()       → cancelled
 *   unpaid  → payment(part)  → partial
 *   unpaid  → payment(full)  → paid
 *   partial → payment(rest)  → paid
 *   unpaid|partial → daily scheduler → overdue (past due_date)
 *
 * amount_paid is denormalized — recomputed inside a transaction every time
 * a payment is created, updated, or deleted (see Invoice::recomputeStatus()).
 *
 * Soft deletes on so historic invoices stay queryable from reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('lease_id');
            $table->foreign('lease_id')->references('id')->on('leases')->restrictOnDelete();

            // Filled by InvoiceNumberGenerator on issue(); nullable while in draft.
            $table->string('invoice_number')->nullable();

            $table->date('billing_period_start');
            $table->date('billing_period_end');

            $table->timestamp('issued_at')->nullable();
            $table->date('due_date');

            // Money — all in TZS cents.
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);

            $table->string('currency', 3)->default('TZS');

            $table->string('status')->default('draft');
            // Values: 'draft' | 'unpaid' | 'partial' | 'paid' | 'overdue' | 'cancelled'

            $table->timestamp('paid_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'lease_id']);
            $table->index(['tenant_id', 'status', 'due_date']);
            // invoice_number is globally unique once issued; partial unique
            // would be cleaner but Laravel's schema builder doesn't expose
            // it portably. A plain unique works because draft invoices keep
            // it null (Postgres treats nulls as distinct in a unique index).
            $table->unique(['tenant_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
