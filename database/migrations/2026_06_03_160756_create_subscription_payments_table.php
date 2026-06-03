<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments a CLIENT makes to BJP for their PMS subscription (SaaS billing).
 * Distinct from the tenant-scoped `payments` table (renter → landlord).
 * Recorded manually by the super-admin for now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            // Client id, denormalised for easy per-client reporting.
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->unsignedBigInteger('amount_tzs'); // minor units (cents)
            $table->date('paid_at');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->string('method')->default('bank_transfer');
            // 'cash' | 'bank_transfer' | 'mobile_money' | 'card' | 'other'
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            // Super-admin who recorded it (super_admin_users.id), nullable.
            $table->unsignedBigInteger('recorded_by')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
