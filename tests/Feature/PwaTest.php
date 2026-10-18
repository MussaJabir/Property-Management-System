<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->client = Client::create(['slug' => 'pwaco', 'name' => 'Madale Heights Apartments', 'status' => 'active', 'brand_primary_color' => '#1d4ed8']);
});

afterEach(fn () => app(PermissionRegistrar::class)->setPermissionsTeamId(null));

it('serves the staff app manifest, installable from /manage', function () {
    $this->get('/manage/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJson([
            'short_name' => 'PMS',
            'start_url' => '/manage',
            'scope' => '/manage',
            'display' => 'standalone',
            'theme_color' => '#0f766e',
        ])
        ->assertJsonPath('icons.2.purpose', 'maskable');

    foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'apple-touch-icon.png'] as $icon) {
        expect(public_path('images/pwa/'.$icon))->toBeFile();
    }
});

it('serves a renter portal manifest branded for the client', function () {
    $this->get('/pwaco/portal/manifest.webmanifest')
        ->assertOk()
        ->assertJson([
            'name' => 'Madale Heights Apartments',
            'short_name' => 'Madale Heigh',
            'start_url' => '/pwaco/portal',
            'scope' => '/pwaco/portal',
            'theme_color' => '#1d4ed8',
        ]);
});

it('serves the service worker uncached, with the offline page it falls back to', function () {
    $response = $this->get('/service-worker.js')
        ->assertOk()
        ->assertHeader('Service-Worker-Allowed', '/')
        ->assertSee("const OFFLINE_URL = '/offline.html';", false)
        ->assertSee("request.mode === 'navigate'", false);

    expect($response->headers->get('Content-Type'))->toContain('application/javascript')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(public_path('offline.html'))->toBeFile();
});

it('puts the install tags on the staff panel and the renter portal, not on the guest rating page', function () {
    $this->get('/manage/login')
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manage/manifest.webmanifest">', false)
        ->assertSee("navigator.serviceWorker.register('/service-worker.js'", false)
        ->assertDontSee('id="pms-install"', false);

    $this->get('/pwaco/portal/login')
        ->assertOk()
        ->assertSee('/pwaco/portal/manifest.webmanifest', false)
        ->assertSee('<meta name="theme-color" content="#1d4ed8">', false);

    $this->get('/pwaco')->assertDontSee('manifest.webmanifest', false);
});

it('offers staff the install prompt once signed in', function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->client->id);
    $owner = User::create([
        'tenant_id' => $this->client->id, 'type' => User::TYPE_OPERATOR, 'name' => 'Owner',
        'email' => 'owner@pwa.test', 'password' => 'secret-password', 'status' => User::STATUS_ACTIVE,
    ]);
    $owner->assignRole('owner');
    $owner->forceFill(['onboarding_completed_at' => now()])->save();

    $this->actingAs($owner, 'web')->get('/manage')
        ->assertOk()
        ->assertSee('id="pms-install"', false)
        ->assertSee('beforeinstallprompt', false);
});
