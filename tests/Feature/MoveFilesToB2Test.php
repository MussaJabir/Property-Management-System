<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\CmsPage;
use App\Models\FeedbackReport;
use App\Models\Location;
use App\Models\Property;
use App\Support\Files;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stancl\Tenancy\Facades\Tenancy;

beforeEach(function () {
    config([
        'filesystems.disks.b2.key' => 'key', 'filesystems.disks.b2.secret' => 'secret',
        'filesystems.disks.b2.bucket' => 'pms-test', 'filesystems.disks.b2.endpoint' => 'https://b2.test',
        'filesystems.disks.b2_public.key' => 'key', 'filesystems.disks.b2_public.secret' => 'secret',
        'filesystems.disks.b2_public.bucket' => 'pms-test-public', 'filesystems.disks.b2_public.endpoint' => 'https://b2.test',
    ]);
    Storage::fake('public');
    Storage::fake('b2');
    Storage::fake('b2_public');

    $this->client = Client::create(['slug' => 'moveco', 'name' => 'Move Co', 'status' => 'active', 'logo_path' => 'client-logos/logo.png']);
    Storage::disk('public')->put('client-logos/logo.png', 'logo-bytes');

    // Website banner on the home page.
    Storage::disk('public')->put('cms/hero/banner.jpg', 'banner-bytes');
    $home = CmsPage::withoutGlobalScopes()->where('tenant_id', 'moveco')->where('slug', CmsPage::SLUG_HOME)->firstOrFail();
    $home->update(['blocks' => [['type' => 'hero', 'data' => ['heading' => 'Hi', 'background_image' => 'cms/hero/banner.jpg']]]]);

    // A public property photo, and a private file kept in the workspace's own folder.
    tenancy()->initialize($this->client);
    $location = Location::create(['name' => 'Sinza', 'region' => 'Dar es Salaam', 'district' => 'Ubungo']);
    $this->property = Property::create(['location_id' => $location->id, 'name' => 'Plaza', 'status' => 'active']);
    $photo = UploadedFile::fake()->image('front.jpg', 20, 20);
    $this->photo = $this->property->addMedia($photo)->toMediaCollection('photos');
    $pdf = UploadedFile::fake()->create('contract.pdf', 4, 'application/pdf');
    $this->privateFile = $this->property->addMedia($pdf)->toMediaCollection('documents', 'local');
    Storage::disk('local')->put('feedback/moveco/shot.png', 'shot-bytes');
    FeedbackReport::create(['type' => 'problem', 'message' => 'Broken', 'status' => 'new', 'screenshot_path' => 'feedback/moveco/shot.png']);
    Tenancy::end();
});

afterEach(function () {
    Tenancy::end();
    File::deleteDirectory(storage_path('tenantmoveco'));
});

it('copies website files to the public bucket and private files to the private one', function () {
    $this->artisan('pms:move-files-to-b2')->assertSuccessful();

    Storage::disk('b2_public')->assertExists('client-logos/logo.png');
    Storage::disk('b2_public')->assertExists('cms/hero/banner.jpg');
    Storage::disk('b2_public')->assertExists($this->photo->getKey().'/front.jpg');
    Storage::disk('b2')->assertExists($this->privateFile->getKey().'/contract.pdf');
    Storage::disk('b2')->assertExists('feedback/moveco/shot.png');

    // Nothing private ends up in the public bucket.
    Storage::disk('b2_public')->assertMissing($this->privateFile->getKey().'/contract.pdf');
    Storage::disk('b2_public')->assertMissing('feedback/moveco/shot.png');

    expect($this->photo->fresh()->disk)->toBe('b2_public')
        ->and($this->photo->fresh()->conversions_disk)->toBe('b2_public')
        ->and($this->privateFile->fresh()->disk)->toBe('b2');

    // Originals stay where they were.
    Storage::disk('public')->assertExists('client-logos/logo.png');
});

it('is safe to run again', function () {
    $this->artisan('pms:move-files-to-b2')->assertSuccessful();

    $this->artisan('pms:move-files-to-b2')
        ->expectsTable(['Files', 'Copied', 'Already on B2', 'Not found'], [
            ['Logos', 0, 1, 0],
            ['Banner photos', 0, 1, 0],
            ['Feedback screenshots', 0, 1, 0],
        ])
        ->assertSuccessful();
});

it('changes nothing on a dry run', function () {
    $this->artisan('pms:move-files-to-b2', ['--dry-run' => true])->assertSuccessful();

    Storage::disk('b2_public')->assertMissing('client-logos/logo.png');
    Storage::disk('b2')->assertMissing('feedback/moveco/shot.png');
    expect(Media::query()->whereIn('disk', ['b2', 'b2_public'])->exists())->toBeFalse();
});

it('refuses to run before the B2 settings are in place', function () {
    config(['filesystems.disks.b2_public.bucket' => null]);

    $this->artisan('pms:move-files-to-b2')->assertFailed();

    Storage::disk('b2')->assertMissing('feedback/moveco/shot.png');
});

it('keeps website files and private files on separate disks', function () {
    expect(Files::publicDisk())->toBe('public');

    config(['filesystems.default' => 'b2']);

    expect(Files::publicDisk())->toBe('b2_public')
        ->and(Files::privateDisk())->toBe('b2')
        ->and($this->client->fresh()->logoUrl())->toContain('client-logos/logo.png');
});
