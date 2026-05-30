<?php

use App\Livewire\Public\ContactForm;
use App\Models\Client;
use App\Models\CmsAnnouncement;
use App\Models\CmsPage;
use App\Models\ContactSubmission;
use App\Models\Location;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ContactSubmissionReceivedNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    $this->client = Client::create([
        'slug' => 'cmsclient',
        'name' => 'CMS Test Co.',
        'status' => 'active',
    ]);
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('seeds the 5 default CMS pages on Client creation', function () {
    $pages = CmsPage::withoutGlobalScopes()
        ->where('tenant_id', $this->client->id)
        ->pluck('slug')
        ->sort()
        ->values()
        ->all();

    expect($pages)->toBe(['about', 'contact', 'home', 'news', 'units']);
});

it('seeded home page has non-empty block content', function () {
    $home = CmsPage::withoutGlobalScopes()
        ->where('tenant_id', $this->client->id)
        ->where('slug', 'home')
        ->first();

    expect($home->blocks)->toBeArray()->not->toBeEmpty();
    expect(collect($home->blocks)->pluck('type'))->toContain('hero');
});

it('resolves the home page at /{tenant}', function () {
    $response = $this->get('/'.$this->client->slug);
    $response->assertOk();
    $response->assertSeeText('CMS Test Co.');
});

it('resolves the about/news/contact pages', function () {
    foreach (['about', 'news', 'contact'] as $slug) {
        $this->get('/'.$this->client->slug.'/'.$slug)->assertOk();
    }
});

it('lists vacant units on the units page with filter support', function () {
    tenancy()->initialize($this->client);

    $location = Location::create(['name' => 'Loc', 'region' => 'Dar', 'district' => 'Kinondoni']);
    $property = Property::create([
        'tenant_id' => tenant('id'),
        'location_id' => $location->id,
        'name' => 'Prop',
        'status' => 'active',
    ]);
    Unit::create([
        'tenant_id' => tenant('id'),
        'property_id' => $property->id,
        'code' => 'A1',
        'type' => 'apartment',
        'status' => Unit::STATUS_VACANT,
        'rent_amount' => 500_00,
        'rent_currency' => 'TZS',
        'billing_cycle' => 'monthly',
    ]);
    Unit::create([
        'tenant_id' => tenant('id'),
        'property_id' => $property->id,
        'code' => 'B1',
        'type' => 'shop',
        'status' => Unit::STATUS_OCCUPIED,
        'rent_amount' => 1000_00,
        'rent_currency' => 'TZS',
        'billing_cycle' => 'monthly',
    ]);
    Tenancy::end();

    $response = $this->get('/'.$this->client->slug.'/units');
    $response->assertOk();
    $response->assertSeeText('A1');
    $response->assertDontSeeText('B1');
});

it('records a contact submission and notifies operators', function () {
    Notification::fake();

    tenancy()->initialize($this->client);

    $operator = User::create([
        'tenant_id' => $this->client->id,
        'type' => User::TYPE_OPERATOR,
        'name' => 'Op A',
        'email' => 'op@example.test',
        'phone' => '+255700000001',
        'password' => bcrypt('x'),
        'status' => 'active',
    ]);

    Livewire::test(ContactForm::class)
        ->set('name', 'Visitor')
        ->set('email', 'visitor@example.test')
        ->set('message', 'Hello there, I would like to learn more about your properties.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    expect(ContactSubmission::query()->count())->toBe(1);

    Notification::assertSentTo($operator, ContactSubmissionReceivedNotification::class);
});

it('renders only published announcements on the news page', function () {
    tenancy()->initialize($this->client);

    CmsAnnouncement::create([
        'tenant_id' => $this->client->id,
        'title' => 'Live news',
        'body' => 'This one is live.',
        'published_at' => now()->subDay(),
    ]);
    CmsAnnouncement::create([
        'tenant_id' => $this->client->id,
        'title' => 'Draft news',
        'body' => 'This one is hidden.',
        'published_at' => null,
    ]);
    Tenancy::end();

    $response = $this->get('/'.$this->client->slug.'/news');
    $response->assertOk();
    $response->assertSeeText('Live news');
    $response->assertDontSeeText('Draft news');
});

it('keeps CMS data isolated between clients', function () {
    $other = Client::create(['slug' => 'otherclient', 'name' => 'Other Co.', 'status' => 'active']);

    $clientPages = CmsPage::withoutGlobalScopes()->where('tenant_id', $this->client->id)->count();
    $otherPages = CmsPage::withoutGlobalScopes()->where('tenant_id', $other->id)->count();

    expect($clientPages)->toBe(5);
    expect($otherPages)->toBe(5);

    tenancy()->initialize($this->client);
    CmsAnnouncement::create([
        'tenant_id' => $this->client->id,
        'title' => 'Client A only',
        'body' => 'Should not appear under Client B',
        'published_at' => now()->subHour(),
    ]);
    Tenancy::end();

    $this->get('/'.$this->client->slug.'/news')->assertSeeText('Client A only');
    $this->get('/'.$other->slug.'/news')->assertDontSeeText('Client A only');
});
