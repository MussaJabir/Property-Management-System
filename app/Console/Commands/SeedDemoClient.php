<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

/**
 * Idempotent demo seeder. Creates a client at /demo-seed (or reuses one
 * with that slug), then populates it with 3 properties, 12 units, 8
 * renters, an active lease on the first 6 occupied units, and 20 invoices
 * spread across paid / partial / unpaid / overdue statuses.
 *
 * Safe to re-run: each row is keyed on a deterministic identifier so the
 * second run is a no-op on existing data.
 */
class SeedDemoClient extends Command
{
    protected $signature = 'demo:seed {--slug=demo-seed : Client slug to populate}';

    protected $description = 'Provision a demo client with sample properties, renters, leases and invoices.';

    public function handle(): int
    {
        $slug = (string) $this->option('slug');
        $this->info("Seeding demo data for /{$slug}…");

        $client = Client::query()->where('slug', $slug)->first()
            ?? Client::create([
                'slug' => $slug,
                'name' => 'Demo Seed Co.',
                'status' => 'active',
                'brand_primary_color' => '#0F766E',
            ]);

        Tenancy::initialize($client);
        app(PermissionRegistrar::class)->setPermissionsTeamId($client->id);

        try {
            $owner = $this->ensureOwner($client);
            $this->info("Owner: {$owner->email} (password: demo1234)");

            $properties = $this->seedProperties();
            $units = $this->seedUnits($properties);
            $renters = $this->seedRenters();
            $leases = $this->seedLeases($renters, $units);
            $this->seedInvoices($leases);

            $this->info('Done.');
        } finally {
            Tenancy::end();
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        }

        return self::SUCCESS;
    }

    protected function ensureOwner(Client $client): User
    {
        $email = 'owner@'.Str::slug($client->slug).'.local';

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'tenant_id' => $client->id,
                'type' => User::TYPE_OPERATOR,
                'name' => 'Demo Owner',
                'email' => $email,
                'phone' => '+255700000999',
                'password' => Hash::make('demo1234'),
                'status' => 'active',
                'locale' => 'en',
                'must_change_password' => false,
            ]);
            $user->assignRole('owner');
        }

        return $user;
    }

    /** @return array<int, Property> */
    protected function seedProperties(): array
    {
        $location = Location::firstOrCreate(
            ['name' => 'Mwenge'],
            ['region' => 'Dar es Salaam', 'district' => 'Kinondoni'],
        );

        $names = ['Mwenge Towers', 'Bahari Apartments', 'Sokoni Commercial'];
        $out = [];

        foreach ($names as $i => $name) {
            $out[] = Property::firstOrCreate(
                ['name' => $name],
                [
                    'location_id' => $location->id,
                    'status' => 'active',
                    'address' => 'Plot '.(101 + $i).', Mwenge',
                ],
            );
        }

        return $out;
    }

    /**
     * @param  array<int, Property>  $properties
     * @return array<int, Unit>
     */
    protected function seedUnits(array $properties): array
    {
        $units = [];
        $codes = [
            [$properties[0], ['A1', 'A2', 'A3', 'A4']],
            [$properties[1], ['B1', 'B2', 'B3', 'B4']],
            [$properties[2], ['Shop 1', 'Shop 2', 'Frame 1', 'Frame 2']],
        ];

        // Curated amenity sets so the demo listing shows realistic, varied tags.
        $amenitySets = [
            ['air_conditioning', 'wifi', 'parking', 'security', 'water_247'],
            ['wifi', 'hot_water', 'furnished', 'balcony', 'ensuite'],
            ['parking', 'cctv', 'backup_power', 'fitted_kitchen', 'security'],
            ['wifi', 'garden', 'servant_quarter', 'parking', 'water_247'],
            ['air_conditioning', 'cctv', 'parking', 'security'],
        ];
        $amenityIndex = 0;

        foreach ($codes as [$property, $list]) {
            foreach ($list as $code) {
                $isCommercial = str_starts_with($code, 'Shop') || str_starts_with($code, 'Frame');

                $units[] = Unit::firstOrCreate(
                    ['property_id' => $property->id, 'code' => $code],
                    [
                        'type' => str_starts_with($code, 'Shop') ? 'shop' : (str_starts_with($code, 'Frame') ? 'business_frame' : 'apartment'),
                        'status' => Unit::STATUS_VACANT,
                        'rent_amount' => rand(150, 800) * 1000 * 100,
                        'rent_currency' => 'TZS',
                        'billing_cycle' => 'monthly',
                        'bedrooms' => $isCommercial ? null : rand(1, 3),
                        'bathrooms' => $isCommercial ? null : rand(1, 2),
                        'amenities' => $amenitySets[$amenityIndex++ % count($amenitySets)],
                    ],
                );
            }
        }

        return $units;
    }

    /** @return array<int, Renter> */
    protected function seedRenters(): array
    {
        $names = [
            'Asha Mwambije', 'John Kileo', 'Maria Nyange', 'Hassan Omar',
            'Grace Mbwambo', 'Salim Juma', 'Neema Massawe', 'Peter Mwakasege',
        ];

        $renters = [];
        foreach ($names as $i => $name) {
            $renters[] = Renter::firstOrCreate(
                ['phone' => '+25571200'.str_pad((string) ($i + 10), 4, '0', STR_PAD_LEFT)],
                [
                    'type' => Renter::TYPE_INDIVIDUAL,
                    'full_name' => $name,
                    'email' => Str::slug($name).'@demo.local',
                ],
            );
        }

        return $renters;
    }

    /**
     * @param  array<int, Renter>  $renters
     * @param  array<int, Unit>  $units
     * @return array<int, Lease>
     */
    protected function seedLeases(array $renters, array $units): array
    {
        $leases = [];

        for ($i = 0; $i < 6; $i++) {
            $renter = $renters[$i];
            $unit = $units[$i];

            $lease = Lease::query()
                ->where('renter_id', $renter->id)
                ->where('unit_id', $unit->id)
                ->first();

            if (! $lease) {
                $lease = Lease::create([
                    'unit_id' => $unit->id,
                    'renter_id' => $renter->id,
                    'status' => Lease::STATUS_PENDING,
                    'start_date' => now()->subMonths(3)->toDateString(),
                    'rent_amount' => $unit->rent_amount,
                    'deposit_amount' => $unit->rent_amount,
                    'currency' => 'TZS',
                    'billing_cycle' => 'monthly',
                    'payment_due_day' => 1,
                ]);

                $lease->activate();
            }

            $leases[] = $lease;
        }

        return $leases;
    }

    /**
     * @param  array<int, Lease>  $leases
     */
    protected function seedInvoices(array $leases): void
    {
        $statuses = [
            ['paid', 3], ['partial', 1], ['unpaid', 1], ['overdue', 1],
        ];

        foreach ($leases as $i => $lease) {
            for ($m = 0; $m < 3; $m++) {
                $periodStart = now()->subMonths(2 - $m)->startOfMonth();
                $periodEnd = $periodStart->copy()->endOfMonth();
                $dueDate = $periodStart->copy()->addDays(5);

                $invoice = Invoice::query()
                    ->where('lease_id', $lease->id)
                    ->whereDate('billing_period_start', $periodStart->toDateString())
                    ->first();

                if ($invoice) {
                    continue;
                }

                $invoice = Invoice::create([
                    'lease_id' => $lease->id,
                    'status' => 'draft',
                    'currency' => 'TZS',
                    'billing_period_start' => $periodStart->toDateString(),
                    'billing_period_end' => $periodEnd->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                ]);

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Monthly rent — '.$periodStart->format('M Y'),
                    'quantity' => 1,
                    'unit_price' => $lease->rent_amount,
                    'line_total' => $lease->rent_amount,
                ]);

                $invoice->refresh()->issue();

                // Pay the older two months for the first half of leases so we
                // see a mix of paid/partial/unpaid invoices on the dashboard.
                if ($m < 2 && $i < 3) {
                    Payment::create([
                        'invoice_id' => $invoice->id,
                        'amount' => $invoice->total_amount,
                        'currency' => 'TZS',
                        'method' => 'cash',
                        'payment_date' => $periodStart->copy()->addDays(3)->toDateString(),
                        'status' => Payment::STATUS_COMPLETED,
                    ]);
                }
            }
        }
    }
}
