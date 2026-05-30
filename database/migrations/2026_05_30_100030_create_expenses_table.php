<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operating expenses against a property (or general overhead when
 * property_id is null). Amount in TZS cents.
 *
 * UUID PK so Spatie Media (which stores model_id as varchar to accommodate
 * UUID-keyed morphable models) doesn't trip Postgres's strict varchar↔integer
 * type checking.
 *
 * Receipt photo attached via Spatie Media on the model.
 *
 * Soft deletes on so deleted rows still appear on year-end reports if a
 * mistaken delete needs reversing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('property_id')->nullable();
            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();

            $table->foreignId('category_id')->constrained('expense_categories')->restrictOnDelete();

            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('TZS');

            $table->date('expense_date');

            $table->text('description')->nullable();

            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'expense_date']);
            $table->index(['tenant_id', 'property_id']);
            $table->index(['tenant_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
