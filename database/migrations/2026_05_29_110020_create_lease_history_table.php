<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log for lease state transitions and key mutations (activate, renew,
 * rent change, terminate, end). The Spatie Activity Log handles generic
 * model auditing, but lease history is queried often enough as a first-class
 * timeline in the operator UI that a dedicated table earns its keep.
 *
 * Soft-deletes intentionally OMITTED — audit rows should never be deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_history', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('lease_id');
            $table->foreign('lease_id')->references('id')->on('leases')->cascadeOnDelete();

            // Who made the change. Nullable for system-triggered events.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action');
            // Values: 'created' | 'activated' | 'renewed' | 'rent_changed' | 'terminated' | 'ended'

            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();

            $table->text('reason')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'lease_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_history');
    }
};
