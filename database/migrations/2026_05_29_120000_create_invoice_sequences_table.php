<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant, per-year monotonic counter feeding the invoice_number format
 *
 *     INV-{tenant_slug}-{year}-{padded_number}    e.g. INV-BEJUS-2026-000041
 *
 * Increment safety is provided at the application layer with a Postgres
 * advisory lock keyed on (tenant_id, year) — see App\Services\InvoiceNumberGenerator.
 *
 * No tenant scoping trait here: this table is written from inside a tenant
 * context but read in a non-scoped way by the generator (which already filters
 * by tenant_id explicitly).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->unsignedSmallInteger('year');

            $table->unsignedBigInteger('last_number')->default(0);

            $table->timestamps();

            $table->primary(['tenant_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
