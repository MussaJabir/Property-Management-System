<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lease = the contract binding a Renter to a Unit for a period of time.
 *
 * rent_amount, deposit_amount are stored as TZS minor units (cents) and are
 * SNAPSHOTTED from the Unit at lease creation so later edits to the unit's
 * advertised rent don't retroactively alter signed contracts.
 *
 * billing_cycle mirrors the unit's expanded set: monthly | quarterly |
 * semi_annual | annual | custom. When custom, billing_cycle_months MUST be set.
 *
 * Status machine:
 *   pending  → activate()   → active
 *   active   → terminate()  → terminated   (early end)
 *   active   → end()        → ended        (natural end at end_date)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('renter_id');
            $table->foreign('renter_id')->references('id')->on('renters')->restrictOnDelete();

            $table->uuid('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();

            $table->date('start_date');
            $table->date('end_date')->nullable();
            // null end_date = open-ended / month-to-month lease.

            // Money in TZS minor units (cents). Snapshot from the unit on creation.
            $table->unsignedBigInteger('rent_amount')->default(0);
            $table->string('currency', 3)->default('TZS');

            $table->unsignedBigInteger('deposit_amount')->default(0);

            $table->string('billing_cycle')->default('monthly');
            // Values: 'monthly' | 'quarterly' | 'semi_annual' | 'annual' | 'custom'
            $table->unsignedSmallInteger('billing_cycle_months')->nullable();

            $table->unsignedTinyInteger('payment_due_day')->default(1);
            // Day of month invoices are due (1-28). Kept <=28 to avoid edge cases
            // with February and 30-day months.

            $table->string('status')->default('pending');
            // Values: 'pending' | 'active' | 'ended' | 'terminated'

            $table->text('terms_notes')->nullable();

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'unit_id', 'status']);
            $table->index(['tenant_id', 'renter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
