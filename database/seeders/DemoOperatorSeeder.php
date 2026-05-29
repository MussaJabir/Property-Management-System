<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a demo Client + an Owner operator user so local dev has something to
 * log into immediately. Run alongside DatabaseSeeder.
 *
 * Idempotent: re-runs are safe.
 */
class DemoOperatorSeeder extends Seeder
{
    public function run(): void
    {
        $proPlan = Plan::where('slug', 'pro')->first();

        $client = Client::firstOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'Demo Properties',
                'contact_email' => 'owner@demo.local',
                'contact_phone' => '+255712000001',
                'status' => 'active',
                'plan_id' => $proPlan?->id,
                'brand_primary_color' => '#0F766E',
            ],
        );

        // ClientObserver will have seeded roles for this client. Tell Spatie
        // which "team" (client) we're operating in for the role assignment.
        app(PermissionRegistrar::class)->setPermissionsTeamId($client->id);

        $owner = User::firstOrCreate(
            ['email' => 'owner@demo.local'],
            [
                'tenant_id' => $client->id,
                'type' => User::TYPE_OPERATOR,
                'name' => 'Demo Owner',
                'phone' => '+255712000001',
                'password' => 'password',
                'locale' => 'en',
                'status' => 'active',
            ],
        );

        $owner->syncRoles(['owner']);

        $this->command->info('Demo client: /demo  ·  operator login: owner@demo.local / password');
    }
}
