<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            // UUID PK so the public CMS routes don't expose incremental ids
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();

            $table->string('name');
            $table->string('type')->default('residential');
            // Values: 'residential' | 'commercial' | 'mixed'

            $table->text('address')->nullable();
            $table->text('description')->nullable();

            $table->string('status')->default('active');
            // Values: 'active' | 'inactive'

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'location_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
