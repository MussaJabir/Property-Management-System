<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance request raised against a unit. Reporter may be an operator or
 * a renter (Phase 8 portal will populate reported_by_user_id from the renter
 * user). Status machine lives on the model:
 *
 *   pending → start() → in_progress → complete() → completed
 *   any non-final → cancel() → cancelled
 *
 * UUID PK so Spatie Media (which stores model_id as varchar to accommodate
 * UUID-keyed morphable models) doesn't trip Postgres's strict varchar↔integer
 * type checking. Same reason Property, Lease, Invoice use UUIDs.
 *
 * Photos via Spatie Media on the model. Soft deletes on so historic requests
 * stay queryable for reports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();

            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description');

            $table->string('priority')->default('medium');
            // Values: 'low' | 'medium' | 'high' | 'urgent'

            $table->string('status')->default('pending');
            // Values: 'pending' | 'in_progress' | 'completed' | 'cancelled'

            $table->timestamp('reported_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Cost in TZS cents — recorded when the work is closed.
            $table->unsignedBigInteger('cost')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'priority']);
            $table->index(['tenant_id', 'unit_id']);
            $table->index(['tenant_id', 'assigned_to_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
