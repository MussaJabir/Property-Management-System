<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            // Days after the due date before an invoice counts as overdue.
            $table->unsignedSmallInteger('grace_days')->default(0)->after('payment_due_day');
            // Days of notice either side must give before leaving.
            $table->unsignedSmallInteger('notice_days')->nullable()->after('grace_days');
            // Late fee added once when an invoice turns overdue: 'fixed'
            // (late_fee_value in minor units) or 'percent' (basis points).
            $table->string('late_fee_type', 10)->nullable()->after('notice_days');
            $table->unsignedBigInteger('late_fee_value')->nullable()->after('late_fee_type');
            // Rent goes up by increase_percent every increase_every_months.
            $table->decimal('increase_percent', 5, 2)->nullable()->after('late_fee_value');
            $table->unsignedSmallInteger('increase_every_months')->nullable()->after('increase_percent');
            $table->date('last_increase_on')->nullable()->after('increase_every_months');
            // A renewal points at the lease it follows; its deposit may be carried over.
            $table->foreignUuid('renewed_from_id')->nullable()->after('unit_id')->constrained('leases')->nullOnDelete();
            $table->unsignedBigInteger('deposit_carried_over')->default(0)->after('deposit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('renewed_from_id');
            $table->dropColumn([
                'grace_days', 'notice_days', 'late_fee_type', 'late_fee_value',
                'increase_percent', 'increase_every_months', 'last_increase_on', 'deposit_carried_over',
            ]);
        });
    }
};
