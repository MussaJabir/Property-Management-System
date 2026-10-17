<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest = a person staying by the night (short stays). Kept apart from
 * renters so the renter list stays about leases. Only name and phone are
 * needed to check someone in; ID details can follow.
 *
 * id_number is encrypted via the model's `encrypted` cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('full_name');
            // E.164, normalized like renter phones.
            $table->string('phone');
            $table->string('email')->nullable();

            $table->string('id_type')->nullable();
            // Values: 'nida' | 'passport' | 'driving_licence' | 'other'
            $table->text('id_number')->nullable();
            $table->string('nationality', 2)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'full_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
