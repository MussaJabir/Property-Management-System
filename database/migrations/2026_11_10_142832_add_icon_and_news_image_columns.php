<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The client's square icon for the browser tab and phone home screen.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('icon_path')->nullable()->after('logo_path');
        });

        // A photo at the top of a news post.
        Schema::table('cms_announcements', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('cms_announcements', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('icon_path');
        });
    }
};
