<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requests sent from a room page on the public website, without signing in:
 * a stay (dates and guests) for rooms let by the night, or an enquiry
 * (move-in date) for rooms let on a lease. Staff confirm or decline them on
 * the front desk; a confirmed stay becomes a Booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('unit_id');
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();

            $table->string('kind');
            // Values: 'stay' | 'enquiry'

            $table->string('full_name');
            $table->string('phone', 20);

            $table->date('check_in_date')->nullable();
            $table->date('check_out_date')->nullable();
            $table->date('move_in_date')->nullable();
            $table->unsignedSmallInteger('guests_count')->default(1);
            $table->text('message')->nullable();

            $table->string('status')->default('new');
            // Values: 'new' | 'confirmed' | 'declined' | 'handled'

            $table->uuid('booking_id')->nullable();
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();

            $table->foreignId('handled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('decline_reason')->nullable();

            $table->string('ip', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
