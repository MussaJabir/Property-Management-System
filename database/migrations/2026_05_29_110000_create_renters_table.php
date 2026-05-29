<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renter ("mpangaji") = the person or business renting a unit. May or may not
 * have a portal user account at this stage; user_id links once a portal login
 * is provisioned (Phase 8).
 *
 * NIDA / TIN are encrypted via Laravel's `encrypted` cast on the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renters', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Portal account link — filled in Phase 8 when a portal user is created.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type')->default('individual');
            // Values: 'individual' | 'business'

            $table->string('full_name');
            $table->string('business_name')->nullable();

            // E.164 (e.g. +255712345678). Normalized via propaganistas/laravel-phone.
            $table->string('phone');
            $table->string('alt_phone')->nullable();
            $table->string('email')->nullable();

            // Encrypted at the application layer. Use TEXT because encrypted
            // payload is significantly longer than the plaintext.
            $table->text('nida_number')->nullable();
            $table->text('tin_number')->nullable();

            $table->text('address')->nullable();

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renters');
    }
};
