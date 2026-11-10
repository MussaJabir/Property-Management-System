<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\Website;
use App\Filament\Operator\Resources\CmsPages\Pages\EditCmsPage;
use App\Models\Client;
use App\Models\CmsAnnouncement;
use App\Models\CmsPage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    Storage::fake('public');

    $this->client = Client::create(['slug' => 'webco', 'name' => 'Web Co', 'status' => 'active', 'contact_phone' => '+255754957395']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@web.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $this->owner->assignRole('owner');
    $this->owner->forceFill(['onboarding_completed_at' => now()])->save();

    tenancy()->initialize($this->client);
    $this->actingAs($this->owner, 'web');
    Filament::setCurrentPanel(Filament::getPanel('operator'));
});

afterEach(function () {
    Tenancy::end();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function webPage(string $slug): CmsPage
{
    return CmsPage::query()->where('slug', $slug)->firstOrFail();
}

/** What a visitor sees at a page of the website. */
function visitSite(string $path = ''): TestResponse
{
    Tenancy::end();

    return test()->get('/webco'.$path);
}

it('shows the website overview with the address and every page', function () {
    Livewire::test(Website::class)
        ->assertOk()
        ->assertSee('/webco')
        ->assertSee('Share on WhatsApp')
        ->assertSee('Home page')
        ->assertSee('Automatic')
        ->assertSee('Hidden until you post')
        ->assertSee(EditCmsPage::getUrl(['record' => webPage('home')]), false);
});

it('opens a page built with the old block builder in the new sections', function () {
    webPage('home')->update(['blocks' => [
        ['type' => 'hero', 'data' => ['heading' => 'Welcome to Web Co', 'cta_label' => 'Browse units', 'cta_link' => 'units']],
        ['type' => 'featured_units', 'data' => ['limit' => 6, 'heading' => 'Available now']],
        ['type' => 'rich_text', 'data' => ['heading' => 'Why choose us', 'body' => "We've been helping renters and owners across Tanzania since day one. Transparent leases, responsive maintenance, and bilingual support — that's the difference."]],
        ['type' => 'image_gallery', 'data' => ['images' => [['url' => 'https://example.com/old.jpg', 'caption' => 'Old']]]],
    ]]);

    Livewire::test(EditCmsPage::class, ['record' => webPage('home')->getKey()])
        ->assertOk()
        ->assertSchemaStateSet([
            'banner.heading' => 'Welcome to Web Co',
            'rooms.show' => true,
            'about.show' => true,
            'about.heading' => 'Why choose us',
            // The old made-up starter text is not carried over.
            'about.body' => null,
            'contact.show' => false,
            'news.show' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Nor does the website show it.
    visitSite()->assertDontSee('since day one')->assertDontSee('Why choose us');
    tenancy()->initialize($this->client);

    // Photos added by web address stay on the page.
    expect(collect(webPage('home')->blocks)->firstWhere('type', 'image_gallery')['data']['images'])
        ->toBe([['url' => 'https://example.com/old.jpg', 'caption' => 'Old']]);
});

it('saves the home page sections and the website follows the switches', function () {
    Livewire::test(EditCmsPage::class, ['record' => webPage('home')->getKey()])
        ->fillForm([
            'banner.heading' => 'Karibu Web Co',
            'banner.subheading' => 'Rooms in Sinza',
            'about.show' => true,
            'about.heading' => 'Why stay with us',
            'about.body' => 'Quiet rooms, 24-hour security.',
            'rooms.show' => false,
            'contact.show' => true,
            'contact.heading' => 'Talk to us',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Saved. Your website is updated.');

    $blocks = collect(webPage('home')->blocks)->keyBy('type');
    expect($blocks->keys()->all())->toBe(['hero', 'featured_units', 'rich_text', 'image_gallery', 'announcements', 'contact_form'])
        ->and($blocks['featured_units']['data']['hidden'] ?? false)->toBeTrue()
        ->and($blocks['rich_text']['data'])->not->toHaveKey('hidden');

    visitSite()->assertOk()
        ->assertSee('Karibu Web Co')
        ->assertSee('Rooms in Sinza')
        ->assertSee('Quiet rooms, 24-hour security.')
        ->assertSee('Talk to us')
        ->assertSee('Call 0754 957 395')
        ->assertDontSee('See all rooms')
        // The banner button needs no setup.
        ->assertSee('See rooms')
        ->assertSee('/webco/units', false);
});

it('keeps a switched-off section so it comes back when switched on', function () {
    $edit = fn () => Livewire::test(EditCmsPage::class, ['record' => webPage('home')->getKey()]);

    $edit()->fillForm(['about.show' => true, 'about.body' => 'Our story here.'])->call('save');
    $edit()->fillForm(['about.show' => false])->call('save');
    visitSite()->assertDontSee('Our story here.');

    tenancy()->initialize($this->client);
    $edit()->assertSchemaStateSet(['about.body' => 'Our story here.'])->fillForm(['about.show' => true])->call('save');
    visitSite()->assertSee('Our story here.');
});

it('uploads photos from the computer and shows them on the website', function () {
    Livewire::test(EditCmsPage::class, ['record' => webPage('home')->getKey()])
        ->fillForm([
            'photos.show' => true,
            'photos.images' => [UploadedFile::fake()->image('front.jpg', 40, 30), UploadedFile::fake()->image('room.jpg', 40, 30)],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $images = collect(webPage('home')->blocks)->firstWhere('type', 'image_gallery')['data']['images'];
    expect($images)->toHaveCount(2);
    Storage::disk('public')->assertExists($images[0]['path']);

    visitSite()->assertSee(Storage::disk('public')->url($images[0]['path']), false);
});

it('shows or hides a whole page with one switch', function () {
    visitSite('/about')->assertNotFound();
    visitSite()->assertDontSee('/webco/about', false);

    tenancy()->initialize($this->client);
    Livewire::test(EditCmsPage::class, ['record' => webPage('about')->getKey()])
        ->fillForm(['show_page' => true, 'title' => 'About Web Co', 'about.body' => 'Family run since 2020.'])
        ->call('save')
        ->assertHasNoFormErrors();

    visitSite('/about')->assertOk()->assertSee('Family run since 2020.');
    visitSite()->assertSee('/webco/about', false);
});

it('puts News in the menu only once there is a post', function () {
    visitSite()->assertDontSee('/webco/news', false);

    tenancy()->initialize($this->client);
    CmsAnnouncement::create(['title' => 'Now open', 'body' => 'Rooms are ready.', 'published_at' => now()->subMinute()]);

    visitSite()->assertSee('/webco/news', false);
});

it('never opens another workspace\'s page', function () {
    $theirs = Client::create(['slug' => 'otherweb', 'name' => 'Other Web', 'status' => 'active']);
    $theirHome = CmsPage::withoutGlobalScopes()->where('tenant_id', $theirs->id)->where('slug', 'home')->firstOrFail();
    Tenancy::end();

    $this->get(EditCmsPage::getUrl(['record' => $theirHome]))->assertNotFound();
});
