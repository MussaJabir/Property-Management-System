<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Money in TZS minor units (cents). 1 TZS = 100 cents.
            // Cast via cknow/laravel-money on the Plan model.
            $table->unsignedBigInteger('price_tzs')->default(0);

            $table->string('billing_period')->default('monthly');
            // Values: monthly | annual

            $table->unsignedInteger('max_properties')->nullable();
            $table->unsignedInteger('max_units')->nullable();
            $table->unsignedInteger('max_operators')->nullable();

            $table->json('features')->nullable();
            $table->boolean('is_public')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
