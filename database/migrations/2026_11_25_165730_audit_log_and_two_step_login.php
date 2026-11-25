<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The audit log: per workspace, and able to point at UUID records
        // (leases, invoices, payments), not only numeric ids.
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('tenant_id')->nullable()->after('id');
            $table->index(['tenant_id', 'created_at']);
        });
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE varchar(36) USING subject_id::varchar');

        // 2-step login with an authenticator app (Filament), plus recovery codes.
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });

        DB::statement("DELETE FROM activity_log WHERE subject_id !~ '^[0-9]+$'");
        DB::statement('ALTER TABLE activity_log ALTER COLUMN subject_id TYPE bigint USING subject_id::bigint');
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropColumn('tenant_id');
        });
    }
};
