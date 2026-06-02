<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Array of amenity keys (see Unit::AMENITIES). jsonb for efficient
            // containment queries when filtering "units with AC", etc.
            $table->jsonb('amenities')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('amenities');
        });
    }
};
