<?php

declare(strict_types=1);

use App\Filament\Operator\Pages\WorkspaceSettings;
use App\Filament\Operator\Resources\CmsAnnouncements\Pages\CreateCmsAnnouncement;
use App\Filament\Operator\Resources\CmsAnnouncements\Pages\EditCmsAnnouncement;
use App\Models\Client;
use App\Models\CmsAnnouncement;
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

    $this->client = Client::create(['slug' => 'newsco', 'name' => 'News Co', 'status' => 'active']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $this->owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Asha',
        'email' => 'asha@news.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
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

function newsSite(string $path = ''): TestResponse
{
    Tenancy::end();

    return test()->get('/newsco'.$path);
}

/** A stored image of the given size on the public disk. */
function storedImage(string $path, int $width, int $height): string
{
    $file = UploadedFile::fake()->image('img.png', $width, $height);
    Storage::disk('public')->put($path, (string) file_get_contents($file->getRealPath()));

    return $path;
}

it('writes a news post with a photo and formatting and shows it right away', function () {
    Livewire::test(CreateCmsAnnouncement::class)
        ->assertSchemaStateSet(['show' => true])
        ->fillForm([
            'title' => 'Now open for short stays',
            'image_path' => UploadedFile::fake()->image('front.jpg', 60, 40),
            'body' => '<p>Our rooms are ready.</p><p><strong>Book</strong> from the Rooms page.</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = CmsAnnouncement::query()->sole();
    expect($post->state())->toBe('shown')
        ->and($post->excerpt)->toBe('Our rooms are ready.')
        ->and($post->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($post->image_path);

    newsSite('/news')->assertOk()
        ->assertSee('Now open for short stays')
        ->assertSee('<strong>Book</strong>', false)
        ->assertSee(Storage::disk('public')->url($post->image_path), false);
});

it('hides a post or holds it for a later date', function () {
    Livewire::test(CreateCmsAnnouncement::class)
        ->fillForm(['title' => 'Coming soon', 'body' => '<p>Soon.</p>', 'show' => true, 'later' => true, 'published_at' => now()->addDays(3)->format('Y-m-d H:i')])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = CmsAnnouncement::query()->sole();
    expect($post->state())->toBe('scheduled');

    Livewire::test(EditCmsAnnouncement::class, ['record' => $post->getKey()])
        ->assertSchemaStateSet(['show' => true, 'later' => true])
        ->fillForm(['show' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($post->fresh()->state())->toBe('hidden');
    newsSite('/news')->assertDontSee('Coming soon');
});

it('keeps old plain-text posts readable and strips anything unsafe', function () {
    CmsAnnouncement::create(['title' => 'Old post', 'body' => "Line one\nLine two", 'published_at' => now()->subDay()]);
    CmsAnnouncement::create(['title' => 'Tricky', 'body' => '<p>Hello</p><script>alert(1)</script>', 'published_at' => now()->subDay()]);

    newsSite('/news')->assertOk()
        ->assertSee('Line one<br />', false)
        ->assertSee('<p>Hello</p>', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('uses the client\'s tab icon, or the PMS one until they add theirs', function () {
    newsSite()->assertSee('<link rel="icon" href="'.asset('favicon.ico').'">', false);

    tenancy()->initialize($this->client);
    Livewire::test(WorkspaceSettings::class)
        ->fillForm(['icon_path' => UploadedFile::fake()->image('mark.png', 64, 64)])
        ->call('save')
        ->assertHasNoErrors();

    $icon = $this->client->fresh()->icon_path;
    expect($icon)->toStartWith('client-icons/');

    newsSite()
        ->assertSee('<link rel="icon" href="'.Storage::disk('public')->url($icon).'">', false)
        ->assertSee('<link rel="apple-touch-icon" href="'.Storage::disk('public')->url($icon).'">', false);
});

it('shows a wide logo in full instead of the name, and keeps square logos as they were', function () {
    $nameInHeader = '<span class="truncate text-[17px] font-bold">News Co</span>';

    $this->client->update(['logo_path' => storedImage('client-logos/wide.png', 300, 100)]);
    newsSite()->assertSee('object-contain', false)->assertDontSee($nameInHeader, false);
    expect($this->client->fresh()->settings['logo_wide'])->toBeTrue();

    $this->client->fresh()->update(['logo_path' => storedImage('client-logos/square.png', 100, 100)]);
    newsSite()->assertSee($nameInHeader, false);
    expect($this->client->fresh()->settings['logo_wide'])->toBeFalse();
});
