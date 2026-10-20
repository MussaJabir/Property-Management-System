<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A rating shows on the public room page only once the landlord approves
 * it ("Show on website"); published_at records when. Changing the rating
 * afterwards hides it again until it is approved anew.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('comment');
            $table->index(['tenant_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'published_at']);
            $table->dropColumn('published_at');
        });
    }
};
