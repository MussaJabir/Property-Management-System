<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items on an invoice. line_total is computed at write time as
 * round(quantity * unit_price) so we never re-derive money from a float.
 *
 * No own tenant_id column — items inherit scoping from their parent invoice.
 * (The parent's tenant_id is still checked on every query through the
 * invoice relationship.)
 *
 * No soft deletes — deleting an item is a real delete; if you need history,
 * the parent invoice's audit log captures it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            $table->uuid('invoice_id');
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();

            $table->string('description');

            $table->decimal('quantity', 10, 2)->default(1);

            // Money — TZS cents.
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('line_total')->default(0);

            $table->string('type')->default('rent');
            // Values: 'rent' | 'utility' | 'fee' | 'deposit' | 'other'

            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
