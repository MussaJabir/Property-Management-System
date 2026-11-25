<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buildings and floors between a property and its units, and rental
 * applications (applicant details, screening notes, documents, decision).
 *
 * Existing units keep working: building and floor are optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('floors')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'property_id']);
            $table->unique(['property_id', 'name']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('building_id')->nullable()->after('property_id')->constrained('buildings')->nullOnDelete();
            // Free text so "Ground", "Mezzanine" and "3" all work.
            $table->string('floor', 20)->nullable()->after('building_id');
            $table->index(['tenant_id', 'building_id']);
        });

        Schema::create('rental_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignUuid('renter_id')->nullable()->constrained('renters')->nullOnDelete();

            $table->string('applicant_name', 150);
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->string('id_number', 50)->nullable();
            $table->string('employer', 150)->nullable();
            $table->unsignedBigInteger('monthly_income')->nullable(); // minor units
            $table->date('move_in_on')->nullable();

            // received | approved | rejected | withdrawn
            $table->string('status', 20)->default('received');
            $table->text('screening_notes')->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_applications');
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'building_id']);
            $table->dropConstrainedForeignId('building_id');
            $table->dropColumn('floor');
        });
        Schema::dropIfExists('buildings');
    }
};
