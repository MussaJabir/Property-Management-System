<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A short stay is billed with an ordinary invoice, so payments, receipts,
 * overdue checks and reports work for it unchanged. Such an invoice belongs
 * to a booking instead of a lease; every invoice has one or the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('lease_id')->nullable()->change();

            $table->uuid('booking_id')->nullable()->after('lease_id');
            $table->foreign('booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->index(['tenant_id', 'booking_id']);
        });

        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_lease_or_booking CHECK (lease_id IS NOT NULL OR booking_id IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_lease_or_booking');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropIndex(['tenant_id', 'booking_id']);
            $table->dropColumn('booking_id');
        });

        // Only possible while no booking invoices remain.
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('lease_id')->nullable(false)->change();
        });
    }
};
