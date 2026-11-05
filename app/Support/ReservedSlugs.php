<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Workspace addresses a client may not take. A client's slug is the first
 * part of every URL (pms.bjptechnologies.co.tz/{slug}/…), so a client named
 * "browse" or "admin" would sit on a PMS page. Covers today's central routes,
 * public files, and words kept for the marketplace and later features.
 */
final class ReservedSlugs
{
    public const LIST = [
        // Central routes and panels
        'admin', 'manage', 'staff', 'privacy', 'locale', 'up', 'storage', 'sanctum',
        'filament', 'flux', 'horizon', 'pulse', 'telescope', 'tenancy', 'livewire',
        'service-worker', 'well-known',
        // Public files
        'build', 'css', 'js', 'fonts', 'images', 'favicon', 'robots', 'offline',
        // Marketplace and planned features
        'browse', 'listings', 'listing', 'nyumba', 'stays', 'post', 'search',
        'api', 'app', 'portal', 'login', 'logout', 'register', 'signup', 'pricing',
        'about', 'contact', 'help', 'support', 'terms', 'blog', 'news', 'www', 'pms', 'bjp',
    ];

    public static function isReserved(string $slug): bool
    {
        $slug = strtolower(trim($slug));

        // Livewire serves its assets under a hashed "livewire-…" prefix.
        return in_array($slug, self::LIST, true) || str_starts_with($slug, 'livewire');
    }
}
