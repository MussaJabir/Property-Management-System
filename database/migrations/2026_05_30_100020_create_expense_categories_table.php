<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightweight per-tenant category for expense classification. Seeded with
 * six defaults (Repair, Cleaning, Security, Utilities, Tax, Other) by
 * ClientObserver::created — same hook that already seeds roles.
 *
 * color is an optional hex (e.g. "#ef4444") used in dashboard charts. No
 * soft deletes — categories are referenced by expenses, so renaming is the
 * right move rather than deletion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('name');
            $table->string('color', 7)->nullable();
            // Hex with leading '#', e.g. '#ef4444'.

            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
