<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('property_id');
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();

            $table->string('code');
            // Display label e.g. "Room 5", "Frame 2A". Unique within property.

            $table->string('type')->default('room');
            // Values: 'room' | 'apartment' | 'business_frame' | 'office' | 'shop' | 'warehouse'

            // Money in TZS minor units (cents). Cast via cknow/laravel-money on the model.
            $table->unsignedBigInteger('rent_amount')->default(0);
            $table->string('rent_currency', 3)->default('TZS');

            $table->string('billing_cycle')->default('monthly');
            // Values: 'monthly' | 'quarterly' | 'annual'

            $table->string('status')->default('vacant');
            // Values: 'vacant' | 'occupied' | 'maintenance' | 'reserved'

            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->decimal('size_sqm', 10, 2)->nullable();

            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'property_id']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['property_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
