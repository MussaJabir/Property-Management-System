<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The history of a request from the public website: staff notes, calls and
 * WhatsApp taps, alerts sent and status changes. A log, so no soft deletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_request_notes', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('booking_request_id');
            $table->foreign('booking_request_id')->references('id')->on('booking_requests')->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 20);
            // Values: 'note' | 'call' | 'whatsapp' | 'alert' | 'status'

            $table->text('body')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'booking_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_request_notes');
    }
};
