<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a user first finishes (or skips) the dashboard onboarding tour we
     * stamp this; null means they have never been through it, which is what
     * triggers the tour to auto-start on their next dashboard load.
     *
     * Both operators and renters authenticate as App\Models\User, so one
     * column serves both the operator panel and the renter portal tours.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('last_login_ip');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
