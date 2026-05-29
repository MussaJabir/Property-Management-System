<?php

use App\Models\Client;
use App\Models\Lease;
use App\Models\Location;
use App\Models\Property;
use App\Models\Renter;
use App\Models\Unit;
use App\Services\LeasePdfGenerator;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create([
        'slug' => 'a',
        'name' => 'Client A',
        'status' => 'active',
    ]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('renders a lease as a non-empty PDF', function () {
    Tenancy::initialize($this->client);

    $loc = Location::create(['name' => 'L', 'region' => 'R', 'district' => 'D']);
    $prop = Property::create([
        'location_id' => $loc->id,
        'name' => 'Tower X',
        'type' => 'residential',
        'status' => 'active',
    ]);
    $unit = Unit::create([
        'property_id' => $prop->id,
        'code' => 'R1',
        'type' => 'room',
        'rent_amount' => 350_000_00,
        'rent_currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'status' => 'vacant',
    ]);
    $renter = Renter::create([
        'type' => 'individual',
        'full_name' => 'Test Renter',
        'phone' => '+255712345678',
    ]);
    $lease = Lease::create([
        'renter_id' => $renter->id,
        'unit_id' => $unit->id,
        'start_date' => now()->toDateString(),
        'rent_amount' => $unit->rent_amount,
        'currency' => 'TZS',
        'billing_cycle' => 'monthly',
        'payment_due_day' => 1,
        'status' => 'pending',
        'terms_notes' => 'Tenant covers electricity. Landlord covers water.',
    ]);

    $bytes = app(LeasePdfGenerator::class)->render($lease);

    // PDF files start with the "%PDF-" magic header.
    expect(substr($bytes, 0, 5))->toBe('%PDF-');
    expect(strlen($bytes))->toBeGreaterThan(1000);
})->skip(getenv('SKIP_PDF_TESTS') === '1', 'PDF tests disabled by SKIP_PDF_TESTS=1');
