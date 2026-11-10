<?php

declare(strict_types=1);

namespace App\Support;

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
