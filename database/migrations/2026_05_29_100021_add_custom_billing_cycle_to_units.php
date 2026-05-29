<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds support for custom billing cycles (e.g. "every 18 months", "every
 * 9 months") and a new built-in "semi_annual" (6-month) option.
 *
 * billing_cycle_months stores the cycle length in months. For standard
 * cycles it can stay null (we derive 1/3/6/12 from the enum at runtime),
 * but it MUST be set when billing_cycle = 'custom'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedSmallInteger('billing_cycle_months')
                ->nullable()
                ->after('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('billing_cycle_months');
        });
    }
};
