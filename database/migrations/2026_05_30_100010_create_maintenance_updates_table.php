<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only timeline of maintenance request progress. Rows are written by:
 *   - the request's state-machine methods (start / complete / cancel),
 *   - manual operator notes from the Updates relation manager.
 *
 * status_change is non-null only on auto-written rows for transitions. Manual
 * notes leave it null.
 *
 * No soft deletes — audit rows are immutable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_updates', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('maintenance_request_id');
            $table->foreign('maintenance_request_id')->references('id')->on('maintenance_requests')->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('note');

            $table->string('status_change')->nullable();
            // Set to the new status when this row was triggered by a transition;
            // null for plain operator notes.

            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'maintenance_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_updates');
    }
};
