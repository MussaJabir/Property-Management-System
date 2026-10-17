<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Booking = a guest's stay in a unit for whole nights, check_in_date up to
 * (not including) check_out_date.
 *
 * Status: reserved → checked_in → checked_out, or reserved → cancelled /
 * no_show. Postgres refuses two live bookings (reserved or checked in) on
 * the same unit for overlapping nights, so a double booking can't slip in
 * even when two receptionists save at the same moment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();

            $table->uuid('guest_id');
            $table->foreign('guest_id')->references('id')->on('guests')->restrictOnDelete();

            $table->date('check_in_date');
            $table->date('check_out_date');

            // Minor units, snapshotted from the unit when booked.
            $table->unsignedBigInteger('nightly_rate');
            $table->string('currency', 3)->default('TZS');

            $table->string('status')->default('reserved');
            // Values: 'reserved' | 'checked_in' | 'checked_out' | 'cancelled' | 'no_show'

            $table->unsignedSmallInteger('guests_count')->default(1);

            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'check_in_date']);
            $table->index(['tenant_id', 'check_out_date']);
            $table->index(['tenant_id', 'guest_id']);
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_dates_check CHECK (check_out_date > check_in_date)');

        // btree_gist lets the exclusion constraint compare unit_id with "=".
        // It is a trusted extension, so the database owner can enable it.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (unit_id WITH =, daterange(check_in_date, check_out_date, '[)') WITH &&)
            WHERE (status IN ('reserved', 'checked_in') AND deleted_at IS NULL)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
