<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Makes PMS installable on a phone or desktop (Phase 5, PWA): the web app
 * manifests and the service worker.
 *
 * Served by Laravel, not as files in public/, because the production
 * nginx caches every on-disk .js for 30 days and that would keep phones
 * on an old service worker. Both go out with no-cache.
 */
class PwaController
{
    public const THEME = '#0f766e';

    /** Staff app: the operator panel. */
    public function operatorManifest(): JsonResponse
    {
        return $this->manifest([
            'id' => '/manage',
            'name' => 'PMS · '.__('Property management'),
            'short_name' => 'PMS',
            'description' => __('Rent, front desk and payments for your properties.'),
            'start_url' => '/manage',
            'scope' => '/manage',
            'theme_color' => self::THEME,
            'shortcuts' => [
                ['name' => __('Today'), 'url' => '/manage'],
                ['name' => __('Front desk'), 'url' => '/manage/front-desk'],
                ['name' => __('Invoices'), 'url' => '/manage/invoices'],
            ],
        ]);
    }

    /** Renter portal, branded per client. */
    public function portalManifest(): JsonResponse
    {
        /** @var Client $client */
        $client = tenant();
        $base = '/'.$client->getKey().'/portal';

        return $this->manifest([
            'id' => $base,
            'name' => $client->name,
            'short_name' => Str::limit($client->name, 12, ''),
            'description' => __('Your rent, receipts and repairs with :client.', ['client' => $client->name]),
            'start_url' => $base,
            'scope' => $base,
            'theme_color' => $client->brand_primary_color ?: self::THEME,
        ]);
    }

    /**
     * The service worker: pages come from the network (with an offline
     * page when there is none), built assets and icons from a cache.
     * Nothing else is cached — no data, forms, Livewire or files.
     */
    public function serviceWorker(): Response
    {
        return response(view('pwa.service-worker', ['version' => $this->version()])->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /** @param  array<string, mixed>  $fields */
    private function manifest(array $fields): JsonResponse
    {
        return response()->json($fields + [
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#ffffff',
            'lang' => app()->getLocale(),
            'icons' => [
                ['src' => '/images/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/images/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/images/pwa/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'no-cache',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Changes with every asset build, so each deploy refreshes the cache. */
    private function version(): string
    {
        $manifest = public_path('build/manifest.json');

        return substr(is_file($manifest) ? (string) md5_file($manifest) : 'dev', 0, 12);
    }
}
