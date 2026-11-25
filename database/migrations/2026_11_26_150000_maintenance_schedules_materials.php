<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preventive maintenance schedules that open work orders when due, a
 * materials register with stock, materials issued against work orders, and a
 * due date on work orders so late jobs can be tracked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 30)->nullable();
            $table->string('priority', 20)->default('medium');
            $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('estimated_cost')->nullable(); // minor units
            // Every N weeks | months | years.
            $table->unsignedSmallInteger('every')->default(1);
            $table->string('period', 10)->default('month');
            $table->date('next_due_on');
            $table->date('last_created_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active', 'next_due_on']);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('unit_of_measure', 20)->default('piece');
            $table->unsignedBigInteger('unit_cost')->default(0); // minor units
            $table->decimal('stock_quantity', 12, 2)->default(0);
            $table->decimal('reorder_level', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('material_issues', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('maintenance_request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->unsignedBigInteger('unit_cost'); // price at the time of issue
            $table->unsignedBigInteger('total_cost');
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['tenant_id', 'maintenance_request_id']);
            $table->index(['tenant_id', 'material_id']);
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->date('due_on')->nullable()->after('approved_at');
            $table->foreignId('maintenance_schedule_id')->nullable()->after('contractor_id')->constrained('maintenance_schedules')->nullOnDelete();
            $table->index(['tenant_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'due_on']);
            $table->dropConstrainedForeignId('maintenance_schedule_id');
            $table->dropColumn('due_on');
        });
        Schema::dropIfExists('material_issues');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('maintenance_schedules');
    }
};
