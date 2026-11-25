<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance work orders: a contractor register, numbered work orders created
 * when a request is approved, a fault type, a cost breakdown (estimate, labour,
 * materials) and a completion sign-off.
 *
 * Existing requests keep working: the new columns are all nullable and the
 * status values only gain 'approved' (between pending and in_progress).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            // Fault type the contractor mainly handles (same values as maintenance categories).
            $table->string('trade', 30)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('work_order_sequences', function (Blueprint $table) {
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->primary(['tenant_id', 'year']);
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            // plumbing | electrical | carpentry | painting | roofing | appliances | security | cleaning | other
            $table->string('category', 30)->nullable()->after('priority');

            $table->string('work_order_number', 60)->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('reported_at');
            $table->foreignId('approved_by_user_id')->nullable()->after('assigned_to_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('contractor_id')->nullable()->after('approved_by_user_id')->constrained('contractors')->nullOnDelete();

            // Minor units, same currency as `cost` (the actual total).
            $table->unsignedBigInteger('estimated_cost')->nullable()->after('cost');
            $table->unsignedBigInteger('labour_cost')->nullable()->after('estimated_cost');
            $table->unsignedBigInteger('materials_cost')->nullable()->after('labour_cost');

            $table->timestamp('signed_off_at')->nullable()->after('completed_at');
            $table->foreignId('signed_off_by_user_id')->nullable()->after('contractor_id')->constrained('users')->nullOnDelete();
            $table->string('sign_off_note', 500)->nullable()->after('signed_off_at');

            $table->unique(['tenant_id', 'work_order_number']);
            $table->index(['tenant_id', 'category']);
            $table->index(['tenant_id', 'contractor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'work_order_number']);
            $table->dropIndex(['tenant_id', 'category']);
            $table->dropIndex(['tenant_id', 'contractor_id']);
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropConstrainedForeignId('contractor_id');
            $table->dropConstrainedForeignId('signed_off_by_user_id');
            $table->dropColumn(['category', 'work_order_number', 'approved_at', 'estimated_cost', 'labour_cost', 'materials_cost', 'signed_off_at', 'sign_off_note']);
        });

        Schema::dropIfExists('work_order_sequences');
        Schema::dropIfExists('contractors');
    }
};
