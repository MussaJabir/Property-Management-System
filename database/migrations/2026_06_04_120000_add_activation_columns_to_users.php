<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renter portal accounts are now activated by the renter through a one-time,
 * expiring link — no password is ever auto-issued (see SECURITY: renter
 * account takeover). These columns hold the hashed activation token and its
 * expiry. Replaces the Phase 8 phone-derived default-password scheme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('activation_token')->nullable()->after('must_change_password');
            $table->timestamp('activation_token_expires_at')->nullable()->after('activation_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['activation_token', 'activation_token_expires_at']);
        });
    }
};
