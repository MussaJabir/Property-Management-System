<?php

declare(strict_types=1);

use App\Livewire\Public\ContactForm;
use App\Models\Client;
use App\Models\ContactSubmission;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create([
        'slug' => 'contactco',
        'name' => 'Contact Co.',
        'status' => 'active',
    ]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('accepts a valid contact submission', function () {
    Notification::fake();
    tenancy()->initialize($this->client);

    Livewire::test(ContactForm::class)
        ->set('name', 'Jane Doe')
        ->set('phone', '+255712000333')
        ->set('message', 'Hello, I am interested in renting a unit.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    expect(ContactSubmission::query()->count())->toBe(1);
});

it('throttles repeated contact submissions from the same IP', function () {
    Notification::fake();
    tenancy()->initialize($this->client);

    $component = Livewire::test(ContactForm::class);

    foreach (range(1, 5) as $i) {
        $component->set('name', 'Jane Doe')
            ->set('phone', '+255712000333')
            ->set('message', 'Message number '.$i.' to the landlord.')
            ->call('submit')
            ->assertHasNoErrors();
    }

    // 6th genuine submission is blocked by the throttle.
    $component->set('name', 'Jane Doe')
        ->set('phone', '+255712000333')
        ->set('message', 'One more message that should be blocked now.')
        ->call('submit')
        ->assertHasErrors('message');

    expect(ContactSubmission::query()->count())->toBe(5);
});

it('silently drops a submission when the honeypot is filled', function () {
    Notification::fake();
    tenancy()->initialize($this->client);

    Livewire::test(ContactForm::class)
        ->set('name', 'Spam Bot')
        ->set('phone', '+255712000444')
        ->set('message', 'Buy cheap things at spam dot example now.')
        ->set('website', 'http://spam.example')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    expect(ContactSubmission::query()->count())->toBe(0);
});
