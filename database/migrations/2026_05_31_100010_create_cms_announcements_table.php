<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements / news posts shown on /{tenant}/news. Slug is auto-derived
 * from the title and unique within the client. published_at gates visibility
 * on the public site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_announcements', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('slug');
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_announcements');
    }
};
