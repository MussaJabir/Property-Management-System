<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A 1–5 rating with an optional comment, from a guest about their stay
 * (via a signed link shared at check-out) or from a renter about living
 * there (renter portal). One per stay; a renter keeps one per lease and
 * can change it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('source');
            // Values: 'guest' | 'renter'

            $table->uuid('booking_id')->nullable()->unique();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();

            $table->uuid('lease_id')->nullable()->unique();
            $table->foreign('lease_id')->references('id')->on('leases')->cascadeOnDelete();

            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'created_at']);
        });

        DB::statement('ALTER TABLE ratings ADD CONSTRAINT ratings_score_range CHECK (score BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE ratings ADD CONSTRAINT ratings_one_subject CHECK ((booking_id IS NULL) <> (lease_id IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
