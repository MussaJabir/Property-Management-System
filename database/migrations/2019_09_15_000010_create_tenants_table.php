<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            // stancl/tenancy expects a string PK; we use UUID v7 values via the
            // generator configured in config/tenancy.php.
            $table->string('id')->primary();

            // PMS-owned columns.
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('brand_primary_color', 7)->nullable();

            $table->string('status')->default('trial');
            // Values: trial | active | suspended | cancelled

            // FK to plans.id added in a later migration (plans table doesn't
            // exist yet at this point in the migration order — stancl's tenants
            // migration uses a 2019 timestamp).
            $table->foreignId('plan_id')->nullable()->index();
            $table->timestamp('trial_ends_at')->nullable();

            // Free-form per-tenant prefs that don't need to be queryable.
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // stancl keeps an arbitrary data column for internal use.
            $table->json('data')->nullable();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
