<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One mobile money payment asked for through Selcom against an invoice:
 * the order we created, the phone the prompt went to, and what Selcom
 * finally said. A completed request points at the Payment it recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->restrictOnDelete();

            // Our ids sent to Selcom: order_id for the order, transid for
            // the push prompt. Both must be unique on Selcom's side.
            $table->string('order_id')->unique();
            $table->string('transid')->unique();

            $table->bigInteger('amount');
            $table->string('currency', 3);
            $table->string('msisdn');

            $table->string('status')->default('pending');
            // Values: 'pending' | 'completed' | 'failed' | 'cancelled' | 'expired'

            // From Selcom once paid.
            $table->string('channel')->nullable();
            $table->string('selcom_reference')->nullable();
            $table->string('selcom_transid')->nullable();

            $table->uuid('payment_id')->nullable();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();

            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('failure_reason')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['status', 'last_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
