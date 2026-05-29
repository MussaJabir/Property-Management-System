<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the FK constraint from tenants.plan_id to plans.id now that plans exists.
 *
 * The tenants table is created by stancl/tenancy's 2019-timestamped migration,
 * which runs before plans is created. This migration patches in the FK after
 * both tables exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreign('plan_id')->references('id')->on('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
        });
    }
};
