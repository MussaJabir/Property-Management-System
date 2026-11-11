<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Where uploaded files live.
 *
 * B2 makes a whole bucket public or private, so PMS uses two:
 *  - the public disk (`b2_public`) for what the website shows: logos,
 *    banners, room and property photos;
 *  - the private disk (`b2`, the default disk) for everything else:
 *    receipts, maintenance photos, expense receipts, lease PDFs.
 * Without B2 (local dev) they fall back to `public` and the default disk.
 */
final class Files
{
    public static function usesB2(): bool
    {
        return config('filesystems.default') === 'b2';
    }

    /** Disk for files the website shows to everyone. */
    public static function publicDisk(): string
    {
        return self::usesB2() ? 'b2_public' : 'public';
    }

    /** Disk for files only signed-in people may open. */
    public static function privateDisk(): string
    {
        return (string) config('filesystems.default');
    }

    /** Public address of a file on the public disk, or null when there is none. */
    public static function publicUrl(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            return Storage::disk(self::publicDisk())->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether a photo on the public disk is tall (portrait or near square)
     * rather than wide. Measured once per file, then remembered.
     */
    public static function isTallImage(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        $key = 'image-shape:'.self::publicDisk().':'.$path;
        $known = Cache::get($key);
        if (is_string($known)) {
            return $known === 'tall';
        }

        try {
            $size = getimagesizefromstring((string) Storage::disk(self::publicDisk())->get($path));
        } catch (Throwable) {
            return false;
        }

        if ($size === false) {
            return false;
        }

        // Wide banners are about 2:1; anything under 1.2:1 loses too much when cropped to that.
        $shape = $size[0] < 1.2 * $size[1] ? 'tall' : 'wide';
        Cache::forever($key, $shape);

        return $shape === 'tall';
    }

    /**
     * Link to a private media file for someone allowed to see it: a short-lived
     * signed link on B2, the plain address elsewhere.
     */
    public static function privateMediaUrl(Media $media, string $conversion = ''): string
    {
        return config("filesystems.disks.{$media->disk}.driver") === 's3'
            ? $media->getTemporaryUrl(now()->addMinutes(30), $conversion)
            : $media->getFullUrl($conversion);
    }
}
