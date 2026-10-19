<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A client's own Selcom merchant credentials, one row per client. Money
 * paid through Selcom settles to the client's own merchant account; PMS
 * only signs the API calls. The key and secret are encrypted at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('selcom_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id')->unique();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('mode')->default('sandbox');
            // Values: 'sandbox' | 'live'

            $table->string('vendor')->nullable();
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();

            $table->boolean('enabled')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('selcom_accounts');
    }
};
