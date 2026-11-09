<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\WorkspaceSettings;
use App\Filament\Operator\Resources\BookingRequests\Pages\ListBookingRequests;
use App\Filament\Operator\Resources\BookingRequests\Pages\ViewBookingRequest;
use App\Jobs\SendRequestSmsAlert;
use App\Livewire\Public\RequestBooking;
use App\Models\BookingRequest;
use App\Models\BookingRequestNote;
use App\Models\Client;
use App\Models\Location;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\BookingRequestReceivedNotification;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'flatco', 'name' => 'Flat Co', 'status' => 'active', 'contact_phone' => '0712000222']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@flat.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Kariakoo', 'region' => 'Dar es Salaam', 'district' => 'Ilala']);
    $property = Property::create(['location_id' => $location->id, 'name' => 'Kariakoo Heights', 'status' => 'active']);
    $this->flat = Unit::create([
        'property_id' => $property->id, 'code' => 'Room 2', 'type' => 'apartment', 'status' => Unit::STATUS_VACANT,
        'rental_mode' => Unit::RENTAL_LONG_TERM, 'rent_amount' => 450_00,
        'rent_currency' => 'USD', 'billing_cycle' => 'monthly',
    ]);

    config(['services.beem.api_key' => 'test-key', 'services.beem.secret' => 'test-secret']);
    Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true])]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function askAbout(Unit $unit, array $data = []): BookingRequest
{
    Livewire::test(RequestBooking::class, ['unit' => $unit])
        ->set(array_merge([
            'full_name' => 'Neema Juma',
            'phone' => '0713 222 333',
            'move_in' => today()->addMonthNoOverflow()->startOfMonth()->toDateString(),
            'message' => "Is the room still free?\nCan I view it on Saturday?",
        ], $data))
        ->call('submit')
        ->assertHasNoErrors();

    return BookingRequest::query()->latest('created_at')->firstOrFail();
}

function smsAlertsTo(Client $client, array $numbers): void
{
    $client->update(['settings' => [...($client->settings ?? []), 'request_sms' => true, 'request_sms_numbers' => $numbers]]);
}

function signInOperator(User $user): void
{
    test()->actingAs($user, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
}

it('opens a request on its own page with everything needed to answer it', function () {
    $request = askAbout($this->flat);
    signInOperator($this->owner);

    Livewire::test(ViewBookingRequest::class, ['record' => $request->getKey()])
        ->assertOk()
        ->assertSee('Neema Juma')
        ->assertSee('0713 222 333')
        ->assertSee('tel:+255713222333', false)
        ->assertSee("Is the room still free?\nCan I view it on Saturday?")
        ->assertSee('Room 2 · Kariakoo Heights')
        ->assertSee('USD 450')
        ->assertSee('First request from this number')
        ->assertSee('Dashboard bell and email to Asha')
        ->assertSee('Request received');

    // And as a real page in the browser.
    Tenancy::end();
    $this->get(route('filament.operator.resources.booking-requests.view', ['record' => $request->getKey()]))
        ->assertOk()
        ->assertSee('Notes and history');
});

it('opens the page from the requests list', function () {
    $request = askAbout($this->flat);
    signInOperator($this->owner);

    Livewire::test(ListBookingRequests::class)
        ->assertSee(route('filament.operator.resources.booking-requests.view', ['record' => $request->getKey()]), false);
});

it('notes earlier requests from the same number', function () {
    askAbout($this->flat);
    $second = askAbout($this->flat, ['message' => 'Asking again']);
    signInOperator($this->owner);

    Livewire::test(ViewBookingRequest::class, ['record' => $second->getKey()])
        ->assertSee('1 earlier request from this number');
});

it('keeps team notes and logs calls and WhatsApp taps', function () {
    $request = askAbout($this->flat);
    signInOperator($this->owner);

    Livewire::test(ViewBookingRequest::class, ['record' => $request->getKey()])
        ->call('saveNote')->assertHasErrors('note')
        ->set('note', 'Called her, coming Saturday 10am')
        ->call('saveNote')->assertHasNoErrors()->assertSet('note', '')
        ->call('logContact', 'call')
        ->call('logContact', 'whatsapp')
        ->call('logContact', 'alert')
        ->assertSee('Called her, coming Saturday 10am')
        ->assertSee('tapped Call');

    expect($request->notes()->pluck('type')->all())->toBe(['whatsapp', 'call', 'note', 'alert'])
        ->and($request->notes()->where('type', 'note')->value('user_id'))->toBe($this->owner->id);
});

it('marks a request as called back from its page and records it', function () {
    $request = askAbout($this->flat);
    signInOperator($this->owner);

    Livewire::test(ViewBookingRequest::class, ['record' => $request->getKey()])
        ->callAction('handled')
        ->assertNotified('Marked as called back');

    expect($request->fresh()->status)->toBe(BookingRequest::STATUS_HANDLED)
        ->and($request->notes()->where('type', BookingRequestNote::TYPE_STATUS)->value('body'))->toBe('Marked as called back');
});

it('never opens another workspace\'s request', function () {
    $request = askAbout($this->flat);
    Tenancy::end();

    $other = Client::create(['slug' => 'otherco', 'name' => 'Other Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($other->id);
    $theirOwner = User::create([
        'tenant_id' => $other->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Baraka',
        'email' => 'baraka@other.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $theirOwner->assignRole('owner');
    $theirOwner->forceFill(['onboarding_completed_at' => now()])->save();

    $this->actingAs($theirOwner, 'web')
        ->get(route('filament.operator.resources.booking-requests.view', ['record' => $request->getKey()]))
        ->assertNotFound();

    $this->get(route('requests.short', ['code' => $request->shortCode()]))->assertNotFound();
});

it('texts the alert numbers one short SMS with a link to the request', function () {
    smsAlertsTo($this->client, ['+255754000111', '+255712000222']);

    $request = askAbout($this->flat);

    Http::assertSentCount(2);
    Http::assertSent(function (Request $sent) use ($request): bool {
        $message = $sent['message'];

        return $sent['recipients'][0]['dest_addr'] === '255754000111'
            && mb_strlen($message) <= SendRequestSmsAlert::MAX_LENGTH
            && str_contains($message, 'Neema Juma (0713 222 333)')
            && str_contains($message, '/r/'.$request->shortCode());
    });

    expect($request->notes()->where('type', 'alert')->pluck('body')->all())
        ->toContain('SMS sent to 0754 000 111, 0712 000 222');
});

it('keeps the SMS within one message when the names are long', function () {
    $this->flat->property->update(['name' => str_repeat('Very Long Property Name ', 5)]);
    $request = askAbout($this->flat, ['full_name' => str_repeat('Neema ', 10)]);

    expect(mb_strlen(SendRequestSmsAlert::message($request->load('unit.property'))))->toBeLessThanOrEqual(160)
        ->and(SendRequestSmsAlert::message($request))->toContain('/r/'.$request->shortCode());
});

it('sends no SMS until the landlord switches it on', function () {
    askAbout($this->flat);

    Http::assertNothingSent();
});

it('skips the email when the landlord switched it off', function () {
    Notification::fake();
    $this->client->update(['settings' => ['request_email' => false]]);

    askAbout($this->flat);

    Notification::assertSentTo($this->owner, BookingRequestReceivedNotification::class,
        fn (BookingRequestReceivedNotification $n, array $channels): bool => $channels === ['database']);
});

it('opens the short link after logging in', function () {
    $request = askAbout($this->flat);
    Tenancy::end();

    $this->get('/r/'.$request->shortCode())->assertRedirect(route('filament.operator.auth.login'));

    $this->actingAs($this->owner, 'web')
        ->get('/r/'.$request->shortCode())
        ->assertRedirect(route('filament.operator.resources.booking-requests.view', ['record' => $request->getKey()]));

    $this->get('/r/00000000')->assertNotFound();
});

it('saves the alert settings with the numbers tidied up', function () {
    signInOperator($this->owner);

    Livewire::test(WorkspaceSettings::class)
        ->set('data.request_sms', true)
        ->set('data.request_email', false)
        ->set('data.request_sms_numbers', ['0754 000 111', '+255754000111', '0712000222'])
        ->call('save')
        ->assertHasNoErrors();

    $settings = $this->client->fresh()->settings;
    expect($settings['request_sms'])->toBeTrue()
        ->and($settings['request_email'])->toBeFalse()
        ->and($settings['request_sms_numbers'])->toBe(['+255754000111', '+255712000222']);

    Livewire::test(WorkspaceSettings::class)
        ->set('data.request_sms', true)
        ->set('data.request_sms_numbers', ['not a phone'])
        ->call('save')
        ->assertHasErrors();
});

it('offers only the buttons that fit the request', function () {
    $request = askAbout($this->flat);
    signInOperator($this->owner);

    Livewire::test(ViewBookingRequest::class, ['record' => $request->getKey()])
        ->assertActionVisible('handled')
        ->assertActionHidden('confirm')
        ->assertDontSee('Confirm &amp; book', false);
});
