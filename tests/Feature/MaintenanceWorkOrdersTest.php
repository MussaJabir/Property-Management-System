<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\Reports\MaintenanceCostPage;
use App\Filament\Operator\Resources\Contractors\Pages\ListContractors;
use App\Filament\Operator\Resources\MaintenanceRequests\Pages\ListMaintenanceRequests;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Reports\Builders\MaintenanceCostReport;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-26 09:00'));
    $this->client = Client::create(['slug' => 'workco', 'name' => 'Work Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@work.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kimironko', 'region' => 'Kigali', 'district' => 'Gasabo']);
    $this->property = Property::create(['location_id' => $location->id, 'name' => 'Kigali Heights', 'status' => 'active']);
    $this->unit = Unit::create(['property_id' => $this->property->id, 'code' => 'A3', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 300_000_00, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $this->plumber = Contractor::create(['name' => 'Juma Hassan', 'company' => 'Maji Fix', 'trade' => 'plumbing', 'phone' => '0712345678']);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function workOrderRequest(string $title = 'Kitchen tap leaking', string $category = 'plumbing'): MaintenanceRequest
{
    return MaintenanceRequest::create([
        'unit_id' => test()->unit->id, 'title' => $title, 'description' => 'Drips all night.',
        'priority' => 'medium', 'category' => $category, 'status' => 'pending', 'reported_at' => now(),
    ]);
}

it('turns an approved request into a numbered work order', function () {
    $first = workOrderRequest();
    $first->approve(userId: $this->owner->id, contractorId: $this->plumber->id, estimatedCents: 50_000_00, note: 'Replace the washer');
    $second = workOrderRequest('Socket sparks', 'electrical');
    $second->approve(userId: $this->owner->id);

    $first->refresh();
    expect($first->status)->toBe(MaintenanceRequest::STATUS_APPROVED)
        ->and($first->work_order_number)->toBe('WO-WORKCO-2026-000001')
        ->and($first->approved_by_user_id)->toBe($this->owner->id)
        ->and($first->contractor?->name)->toBe('Juma Hassan')
        ->and($first->estimated_cost)->toBe(50_000_00)
        ->and($first->updates()->first()?->status_change)->toBe('approved')
        ->and($second->fresh()?->work_order_number)->toBe('WO-WORKCO-2026-000002');

    expect(fn () => $first->approve())->toThrow(RuntimeException::class);
});

it('approves a request that is started straight away', function () {
    $request = workOrderRequest();
    $request->start(userId: $this->owner->id);

    expect($request->fresh()?->status)->toBe(MaintenanceRequest::STATUS_IN_PROGRESS)
        ->and($request->fresh()?->work_order_number)->toBe('WO-WORKCO-2026-000001')
        ->and($request->fresh()?->approved_at)->not->toBeNull();

    $approved = workOrderRequest('Door hinge');
    $approved->approve();
    $approved->start();
    expect($approved->fresh()?->status)->toBe(MaintenanceRequest::STATUS_IN_PROGRESS)
        ->and($approved->fresh()?->work_order_number)->toBe('WO-WORKCO-2026-000002');
});

it('adds labour and materials into the actual cost and compares it with the estimate', function () {
    $request = workOrderRequest();
    $request->approve(estimatedCents: 50_000_00);
    $request->start();
    $request->complete(labourCents: 30_000_00, materialsCents: 35_000_00);

    $request->refresh();
    expect($request->cost)->toBe(65_000_00)
        ->and($request->labour_cost)->toBe(30_000_00)
        ->and($request->costVariance())->toBe(15_000_00);

    // Entered totals win over the sum.
    $other = workOrderRequest('Paint');
    $other->start();
    $other->complete(costCents: 10_000_00, labourCents: 4_000_00);
    expect($other->fresh()?->cost)->toBe(10_000_00);
});

it('signs off finished work once', function () {
    $request = workOrderRequest();
    expect(fn () => $request->signOff())->toThrow(RuntimeException::class);

    $request->start();
    $request->complete();
    $request->signOff($this->owner->id, 'Checked with the tenant');

    $request->refresh();
    expect($request->isSignedOff())->toBeTrue()
        ->and($request->signed_off_by_user_id)->toBe($this->owner->id)
        ->and($request->sign_off_note)->toBe('Checked with the tenant');
    expect(fn () => $request->signOff())->toThrow(RuntimeException::class);
});

it('keeps contractors to their own client', function () {
    expect(Contractor::query()->count())->toBe(1)
        ->and($this->plumber->fresh()?->getRawOriginal('phone'))->toBe('+255712345678');

    Tenancy::end();
    $other = Client::create(['slug' => 'otherwork', 'name' => 'Other Work', 'status' => 'active']);
    tenancy()->initialize($other);

    expect(Contractor::query()->count())->toBe(0);
});

it('reports maintenance spend by job and by property', function () {
    $request = workOrderRequest();
    $request->approve(contractorId: $this->plumber->id, estimatedCents: 50_000_00);
    $request->start();
    $request->complete(labourCents: 30_000_00, materialsCents: 35_000_00);
    workOrderRequest('Still open');

    $report = new MaintenanceCostReport(from: now()->startOfMonth(), to: now()->endOfMonth());
    $row = $report->rows()->sole();

    expect($row['work_order'])->toBe('WO-WORKCO-2026-000001')
        ->and($row['type'])->toBe('Plumbing')
        ->and($row['done_by'])->toBe('Juma Hassan (Maji Fix)')
        ->and($row['actual'])->toBe('65,000')
        ->and($row['variance'])->toBe('+15,000')
        ->and($report->summary()['Kigali Heights'])->toBe('TZS 65,000')
        ->and($report->summary()['Over estimate'])->toBe('1')
        ->and($report->summary()['Total maintenance cost'])->toBe('TZS 65,000');

    expect((new MaintenanceCostReport(from: now()->startOfMonth(), to: now()->endOfMonth(), category: 'electrical'))->rows())->toHaveCount(0);
});

it('lets staff approve and sign off from the maintenance list', function () {
    $request = workOrderRequest();
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(ListMaintenanceRequests::class)
        ->callTableAction('approve', $request, data: ['contractor' => $this->plumber->id, 'estimate' => 50000])
        ->assertHasNoTableActionErrors();

    expect($request->fresh()?->status)->toBe('approved')
        ->and($request->fresh()?->estimated_cost)->toBe(50_000_00);

    $request->fresh()?->start();
    Livewire::test(ListMaintenanceRequests::class)
        ->callTableAction('complete', $request, data: ['labour' => 20000, 'materials' => 5000])
        ->callTableAction('signOff', $request, data: ['note' => 'All good']);

    expect($request->fresh()?->cost)->toBe(25_000_00)
        ->and($request->fresh()?->isSignedOff())->toBeTrue();

    Livewire::test(ListContractors::class)->assertOk()->assertSee('Juma Hassan');
    Livewire::test(MaintenanceCostPage::class)->assertOk()->assertSee('WO-WORKCO-2026-000001');
});
