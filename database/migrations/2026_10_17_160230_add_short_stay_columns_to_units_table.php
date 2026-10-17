<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short stays: a unit can be let long-term (leases), by the night
 * (bookings), or both. nightly_rate is in minor units like rent_amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('rental_mode')->default('long_term')->after('status');
            // Values: 'long_term' | 'short_stay' | 'both'
            $table->unsignedBigInteger('nightly_rate')->nullable()->after('rental_mode');

            $table->index(['tenant_id', 'rental_mode']);
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'rental_mode']);
            $table->dropColumn(['rental_mode', 'nightly_rate']);
        });
    }
};
