<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the default users table with the columns needed for client-scoped
 * operator + renter users:
 *
 *   tenant_id   — FK to tenants.id; nullable to allow seeders/factory rows
 *                 before a client exists, but every real operator/renter row
 *                 must have one. Indexed.
 *   type        — 'operator' (landlord staff) | 'renter' (mpangaji)
 *   phone       — E.164 nullable; required for renters whose primary contact
 *                 is phone (most TZ renters don't keep an active email)
 *   locale      — 'en' | 'sw'; falls back to config('app.locale')
 *   status      — 'active' | 'disabled'
 *   last_login_at, last_login_ip — audit trail
 *
 * Email is made nullable so renter accounts (phone-only) can exist.
 * The unique constraint on email becomes a partial unique (where not null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the legacy unique constraint so we can re-add as partial
            $table->dropUnique('users_email_unique');
            $table->string('email')->nullable()->change();

            $table->string('tenant_id')->nullable()->after('id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('type')->default('operator')->after('tenant_id');
            // Values: 'operator' (landlord staff) | 'renter' (mpangaji)

            $table->string('phone')->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');

            $table->string('locale', 2)->default('en')->after('password');
            $table->string('status')->default('active')->after('locale');

            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'phone']);
        });

        // Partial unique on email (Postgres) — null emails (renters) are allowed
        // but any provided email must be unique within the platform.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE email IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_email_unique');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id', 'type']);
            $table->dropIndex(['tenant_id', 'phone']);
            $table->dropColumn([
                'tenant_id', 'type', 'phone', 'phone_verified_at',
                'locale', 'status', 'last_login_at', 'last_login_ip',
            ]);
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }
};
