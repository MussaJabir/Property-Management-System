<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\FrontDesk;
use App\Filament\Operator\Pages\FrontDeskCalendar;
use App\Filament\Operator\Pages\WorkspaceSettings;
use App\Filament\Operator\Widgets\FrontDeskTodayWidget;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\FrontDesk\ShortStayService;
use Database\Seeders\PlanSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'calco', 'name' => 'Cal Co', 'status' => 'active']);
    $this->travelTo(Carbon::parse('2026-10-17 09:00'));

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Owner',
        'email' => 'owner@cal.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Madale', 'region' => 'Dar', 'district' => 'Kinondoni']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Lodge', 'status' => 'active']);
    $this->room = Unit::create([
        'property_id' => $property->id, 'code' => 'R7', 'type' => 'room', 'status' => Unit::STATUS_VACANT,
        'rent_amount' => 0, 'rent_currency' => 'TZS', 'billing_cycle' => 'monthly',
        'rental_mode' => Unit::RENTAL_SHORT_STAY, 'nightly_rate' => 50_000_00,
    ]);

    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('shows two weeks of stays and books a free night from the calendar', function () {
    app(ShortStayService::class)->book($this->room, ['full_name' => 'Asha Bakari', 'phone' => '0712555666'], today(), today()->addDays(3));

    Livewire::test(FrontDeskCalendar::class)
        ->assertSee('Asha Bakari')
        ->assertSee('R7')
        ->mountAction(TestAction::make('cellBook')->arguments(['unit' => $this->room->id, 'date' => '2026-10-22']))
        ->assertSchemaStateSet(['unit_id' => $this->room->id, 'check_in_date' => '2026-10-22', 'check_out_date' => '2026-10-23', 'nightly_rate' => 50_000], 'mountedActionSchema0')
        ->setActionData(['full_name' => 'Juma Said', 'phone' => '0713000111'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSee('Juma Said');

    expect(Booking::query()->count())->toBe(2)
        ->and(Booking::query()->where('check_in_date', '2026-10-22')->exists())->toBeTrue();

    // A week later the first stay is out of view.
    Livewire::test(FrontDeskCalendar::class)->call('shift', 7)->assertDontSee('Asha Bakari')->assertSet('from', '2026-10-24');
});

it('saves check-in and check-out times and flags guests still in after check-out', function () {
    Livewire::test(WorkspaceSettings::class)
        ->assertSet('data.check_out_time', fn (?string $time): bool => str_contains((string) $time, '10:00'))
        ->set('data.check_in_time', '12:00')
        ->set('data.check_out_time', '11:00')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->client->fresh()?->settings['check_in_time'])->toBe('12:00')
        ->and($this->client->fresh()?->settings['check_out_time'])->toBe('11:00');

    app(ShortStayService::class)->book($this->room, ['full_name' => 'Late Leaver', 'phone' => '0712000999'], today()->subDay(), today(), checkInNow: true);

    Livewire::test(FrontDesk::class)->assertSee('check-out by 11:00')->assertDontSee('past 11:00');

    $this->travelTo(Carbon::parse('2026-10-17 11:30'));
    Livewire::test(FrontDesk::class)->assertSee('past 11:00');
});

it('puts arrivals and departures on Today for workspaces with short stays only', function () {
    app(ShortStayService::class)->book($this->room, ['full_name' => 'Asha Bakari', 'phone' => '0712555666'], today(), today()->addDay());

    expect(FrontDeskTodayWidget::canView())->toBeTrue();
    Livewire::test(FrontDeskTodayWidget::class)->assertSee('Arriving today')->assertSee('Asha Bakari');

    $this->seed(PlanSeeder::class);
    $this->client->forceFill(['plan_id' => Plan::query()->where('slug', 'starter')->value('id')])->save();
    $this->owner->refresh();
    expect(FrontDeskTodayWidget::canView())->toBeFalse()
        ->and(FrontDeskCalendar::canAccess())->toBeFalse();
});
