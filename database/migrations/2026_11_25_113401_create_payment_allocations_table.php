<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One payment can pay several invoices. payment_allocations says how much of
 * each payment went to which invoice; what is left unallocated is the payer's
 * credit. payments.invoice_id stays as the main (first) invoice for receipts
 * and lists, and may be null for a payment made in advance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('amount'); // minor units
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'payment_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('invoice_id')->nullable()->change();
            // Whose money: lets credit and statements follow the renter, not one invoice.
            $table->foreignUuid('renter_id')->nullable()->after('invoice_id')->constrained('renters')->nullOnDelete();
            // A payment is never deleted once completed: it is reversed, with a reason.
            $table->timestamp('reversed_at')->nullable()->after('status');
            $table->foreignId('reversed_by_user_id')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable()->after('reversed_by_user_id');
            $table->index(['tenant_id', 'renter_id']);
        });

        // Every existing payment becomes one allocation of its full amount to
        // its invoice, so every invoice keeps exactly the balance it has now.
        DB::statement(<<<'SQL'
            INSERT INTO payment_allocations (tenant_id, payment_id, invoice_id, amount, created_at, updated_at)
            SELECT p.tenant_id, p.id, p.invoice_id, p.amount, p.created_at, p.updated_at
            FROM payments p
            WHERE p.invoice_id IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE payments p
            SET renter_id = l.renter_id
            FROM invoices i
            JOIN leases l ON l.id = i.lease_id
            WHERE i.id = p.invoice_id AND p.renter_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'renter_id']);
            $table->dropConstrainedForeignId('reversed_by_user_id');
            $table->dropConstrainedForeignId('renter_id');
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });

        Schema::dropIfExists('payment_allocations');
    }
};
