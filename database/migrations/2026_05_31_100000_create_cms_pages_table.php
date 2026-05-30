<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 — CMS pages.
 *
 * Each client has a fixed set of pages (home, about, units, news, contact),
 * seeded on Client creation. The operator edits each page's blocks via a
 * Filament Builder field; blocks are stored as a JSON array of
 * { type: <slug>, data: {...} } objects.
 *
 * slug is unique within a client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('slug'); // home | about | units | news | contact
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->json('blocks')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
