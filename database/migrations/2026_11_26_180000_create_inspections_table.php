<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move-in, move-out and routine inspections of a unit: the condition of each
 * area, meter readings, notes and photos, completed by a named inspector.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignUuid('lease_id')->nullable()->constrained('leases')->nullOnDelete();
            // move_in | move_out | routine
            $table->string('type', 20);
            $table->date('inspected_on');
            $table->foreignId('inspector_user_id')->nullable()->constrained('users')->nullOnDelete();
            // [{area, condition, note}]
            $table->json('items')->nullable();
            $table->string('electricity_reading', 30)->nullable();
            $table->string('water_reading', 30)->nullable();
            $table->unsignedSmallInteger('keys_handed')->nullable();
            $table->text('notes')->nullable();
            $table->string('renter_present', 150)->nullable();
            // draft | completed
            $table->string('status', 20)->default('draft');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'unit_id']);
            $table->index(['tenant_id', 'lease_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
