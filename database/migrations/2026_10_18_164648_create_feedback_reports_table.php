<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback from a client's staff to BJP: a problem, an idea or a question,
 * with an optional screenshot. Read and answered in the super admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_reports', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type')->default('problem');
            // Values: 'problem' | 'idea' | 'question'
            $table->text('message');
            $table->string('page_url', 500)->nullable();
            // Path on the default disk (B2 in production), never public.
            $table->string('screenshot_path')->nullable();

            $table->string('status')->default('new');
            // Values: 'new' | 'in_progress' | 'resolved'
            $table->text('reply')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_reports');
    }
};
