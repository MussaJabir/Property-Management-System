<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\Reports\MaintenancePerformancePage;
use App\Filament\Operator\Resources\MaintenanceRequests\Pages\ListMaintenanceRequests;
use App\Filament\Operator\Resources\MaintenanceSchedules\Pages\ListMaintenanceSchedules;
use App\Filament\Operator\Resources\Materials\Pages\ListMaterials;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\Material;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Reports\Builders\MaintenancePerformanceReport;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-26 09:00'));
    $this->client = Client::create(['slug' => 'prevco', 'name' => 'Prev Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@prev.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kimironko', 'region' => 'Kigali', 'district' => 'Gasabo']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Kigali Heights', 'status' => 'active']);
    $this->unit = Unit::create(['property_id' => $property->id, 'code' => 'A3', 'status' => Unit::STATUS_OCCUPIED, 'rent_amount' => 0, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly']);
    $this->electrician = Contractor::create(['name' => 'Eric Habimana', 'trade' => 'electrical']);
    $this->washers = Material::create(['name' => 'Tap washer', 'unit_of_measure' => 'piece', 'unit_cost' => 2_500_00, 'stock_quantity' => 10, 'reorder_level' => 4]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function prevRequest(string $title = 'Tap leaking', string $category = 'plumbing', string $reportedAt = '2026-11-26 08:00', string $priority = 'medium'): MaintenanceRequest
{
    return MaintenanceRequest::create([
        'unit_id' => test()->unit->id, 'title' => $title, 'description' => 'Details.', 'priority' => $priority,
        'category' => $category, 'status' => 'pending', 'reported_at' => $reportedAt,
    ]);
}

it('opens a work order when a schedule falls due and moves the date on', function () {
    $schedule = MaintenanceSchedule::create([
        'unit_id' => $this->unit->id, 'title' => 'Service the generator', 'category' => 'electrical',
        'priority' => 'medium', 'contractor_id' => $this->electrician->id, 'estimated_cost' => 80_000_00,
        'every' => 3, 'period' => 'month', 'next_due_on' => '2026-11-26',
    ]);
    $later = MaintenanceSchedule::create([
        'unit_id' => $this->unit->id, 'title' => 'Clean water tank', 'every' => 1, 'period' => 'year', 'next_due_on' => '2027-01-10',
    ]);

    $this->artisan('maintenance:run-schedules')->assertSuccessful();

    $order = MaintenanceRequest::query()->where('maintenance_schedule_id', $schedule->id)->sole();
    expect($order->status)->toBe('approved')
        ->and($order->work_order_number)->toBe('WO-PREVCO-2026-000001')
        ->and($order->contractor_id)->toBe($this->electrician->id)
        ->and($order->estimated_cost)->toBe(80_000_00)
        ->and($order->due_on?->toDateString())->toBe('2026-11-26')
        ->and($schedule->fresh()?->next_due_on->toDateString())->toBe('2027-02-26')
        ->and($schedule->fresh()?->frequencyLabel())->toBe('Every 3 months')
        ->and($later->workOrders()->count())->toBe(0);

    // Running again the same day opens nothing new.
    $this->artisan('maintenance:run-schedules')->assertSuccessful();
    expect(MaintenanceRequest::query()->count())->toBe(1);
});

it('skips missed dates instead of opening a pile of work orders', function () {
    $schedule = MaintenanceSchedule::create([
        'unit_id' => $this->unit->id, 'title' => 'Check fire extinguishers', 'every' => 1, 'period' => 'week', 'next_due_on' => '2026-10-01',
    ]);

    $schedule->createWorkOrder();

    expect(MaintenanceRequest::query()->count())->toBe(1)
        ->and($schedule->fresh()?->next_due_on->gt(today()))->toBeTrue();
});

it('issues materials from stock and adds them to the job cost', function () {
    $request = prevRequest();
    expect(fn () => $request->issueMaterial($this->washers, 2))->toThrow(RuntimeException::class);

    $request->approve();
    $issue = $request->issueMaterial($this->washers, 3, $this->owner->id);

    expect($issue->total_cost)->toBe(7_500_00)
        ->and($this->washers->fresh()?->stock_quantity)->toBe('7.00')
        ->and($request->fresh()?->materials_cost)->toBe(7_500_00);

    $request->issueMaterial($this->washers, 3);
    expect($this->washers->fresh()?->needsReorder())->toBeTrue();

    expect(fn () => $request->issueMaterial($this->washers, 5))->toThrow(RuntimeException::class, 'Only 4 piece of Tap washer in stock.');

    $request->start();
    $request->complete(labourCents: 10_000_00);
    expect($request->fresh()?->cost)->toBe(25_000_00);
});

it('tracks overdue jobs, on-time finishes and repeat faults', function () {
    $late = prevRequest('Socket sparks', 'electrical', '2026-11-10 08:00', 'urgent');
    $late->approve(contractorId: $this->electrician->id, dueOn: '2026-11-12');
    expect($late->fresh()?->isOverdue())->toBeTrue()
        ->and(MaintenanceRequest::query()->overdue()->count())->toBe(1);

    $first = prevRequest('Tap leaking', 'plumbing', '2026-10-01 08:00');
    $first->approve(dueOn: '2026-10-08');
    $first->start();
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    $first->complete(costCents: 20_000_00);
    $this->travelTo(Carbon::parse('2026-11-26 09:00'));

    $again = prevRequest('Tap leaking again', 'plumbing', '2026-11-20 08:00');
    $other = prevRequest('Paint wall', 'painting', '2026-11-20 08:00');

    expect($first->fresh()?->wasOnTime())->toBeTrue()
        ->and($again->isRepeat())->toBeTrue()
        ->and($first->fresh()?->isRepeat())->toBeFalse()
        ->and($other->isRepeat())->toBeFalse()
        ->and(MaintenanceRequest::query()->repeatFaults()->pluck('id')->all())->toBe([$again->id]);

    $report = new MaintenancePerformanceReport(from: Carbon::parse('2026-10-01'), to: Carbon::parse('2026-11-30'));
    $rows = $report->rows()->keyBy('who');
    expect($rows['Eric Habimana']['overdue_open'])->toBe('1')
        ->and($rows['Not assigned']['finished'])->toBe('1')
        ->and($rows['Not assigned']['on_time_rate'])->toBe('100%')
        ->and($report->summary()['Open and overdue now'])->toBe('1');
});

it('shows schedules, materials and the new actions in the panel', function () {
    MaintenanceSchedule::create(['unit_id' => $this->unit->id, 'title' => 'Service the generator', 'every' => 1, 'period' => 'month', 'next_due_on' => '2026-12-01']);
    $request = prevRequest();
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));

    Livewire::test(ListMaintenanceSchedules::class)->assertOk()->assertSee('Service the generator')->assertSee('Every month');
    Livewire::test(ListMaterials::class)->assertOk()->assertSee('Tap washer');

    Livewire::test(ListMaintenanceRequests::class)
        ->callTableAction('approve', $request, data: ['due_on' => '2026-12-01'])
        ->callTableAction('issueMaterials', $request, data: ['material' => $this->washers->id, 'quantity' => 2])
        ->assertHasNoTableActionErrors();

    expect($request->fresh()?->due_on?->toDateString())->toBe('2026-12-01')
        ->and($request->fresh()?->materials_cost)->toBe(5_000_00);

    Livewire::test(MaintenancePerformancePage::class)->assertOk();
});
