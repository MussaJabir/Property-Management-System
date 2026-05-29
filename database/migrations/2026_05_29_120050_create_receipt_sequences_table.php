<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant, per-year monotonic counter for receipt numbers — the receipts
 * twin of invoice_sequences. Receipts are issued by the PaymentObserver when
 * a payment moves to `completed`.
 *
 * Format: RCP-{tenant_slug}-{year}-{padded_number}.
 *
 * Concurrency safety identical to invoice_sequences: Postgres advisory lock
 * keyed by (tenant_id, year) inside App\Services\ReceiptNumberGenerator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_sequences', function (Blueprint $table) {
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->unsignedSmallInteger('year');

            $table->unsignedBigInteger('last_number')->default(0);

            $table->timestamps();

            $table->primary(['tenant_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_sequences');
    }
};
