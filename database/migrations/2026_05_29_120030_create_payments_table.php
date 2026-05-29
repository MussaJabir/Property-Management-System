<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment = money received against a specific invoice. Manual entry in v1
 * (cash, bank transfer, mobile money, cheque, card). Selcom Pay integration
 * lands in v2 and feeds the same table via an automated flow.
 *
 * amount is TZS cents. status is independent of method — a `mobile_money`
 * payment can sit in `pending` until reconciled.
 *
 * Soft deletes on so we can undo erroneous entries without losing the audit
 * trail (the invoice's amount_paid is recomputed on delete via observer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();

            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('TZS');

            $table->date('payment_date');

            $table->string('method');
            // Values: 'cash' | 'bank_transfer' | 'mobile_money' | 'cheque' | 'card'

            $table->string('reference_number')->nullable();

            $table->string('mobile_money_provider')->nullable();
            // Values when method='mobile_money': 'mpesa' | 'tigopesa' | 'airtelmoney' | 'halopesa'

            $table->string('transaction_id')->nullable();
            // External gateway / network transaction id

            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('completed');
            // Values: 'pending' | 'completed' | 'failed' | 'refunded'

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'payment_date']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
