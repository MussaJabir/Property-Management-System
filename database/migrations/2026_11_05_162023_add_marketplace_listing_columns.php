<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PMS marketplace (Phase B, /browse):
 * - units.on_marketplace: the landlord's opt-in per unit (off by default)
 * - units.featured_until: paid boost, shown first until this time
 * - tenants.is_demo: demo workspaces are labelled "Demo" and never bookable
 * - the `marketplace_listing` plan feature on every paid plan
 */
return new class extends Migration
{
    private const PLANS = ['basic', 'starter', 'pro', 'enterprise'];

    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->boolean('on_marketplace')->default(false);
            $table->timestamp('featured_until')->nullable();
            $table->index(['tenant_id', 'on_marketplace']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false);
        });

        foreach (self::PLANS as $slug) {
            $features = json_decode((string) DB::table('plans')->where('slug', $slug)->value('features'), true) ?: null;

            if (is_array($features) && ! in_array('marketplace_listing', $features, true)) {
                $features[] = 'marketplace_listing';
                DB::table('plans')->where('slug', $slug)->update(['features' => json_encode($features), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::PLANS as $slug) {
            $features = json_decode((string) DB::table('plans')->where('slug', $slug)->value('features'), true) ?: null;

            if (is_array($features)) {
                $features = array_values(array_diff($features, ['marketplace_listing']));
                DB::table('plans')->where('slug', $slug)->update(['features' => json_encode($features), 'updated_at' => now()]);
            }
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'on_marketplace']);
            $table->dropColumn(['on_marketplace', 'featured_until']);
        });
    }
};
