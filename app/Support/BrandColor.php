<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A landlord's brand colour, made safe for white text on buttons and for
 * prices on a white page. Bright colours (a neon green, a yellow) are
 * darkened step by step until white text on them is readable (WCAG 4.5:1);
 * colours that already pass are returned unchanged.
 */
final class BrandColor
{
    public const FALLBACK = '#0F766E';

    public static function readable(?string $hex): string
    {
        $rgb = self::parse($hex) ?? self::parse(self::FALLBACK);
        assert($rgb !== null);

        for ($i = 0; $i < 20 && self::contrastWithWhite($rgb) < 4.5; $i++) {
            $rgb = array_map(fn (float $c): float => $c * 0.9, $rgb);
        }

        return sprintf('#%02X%02X%02X', ...array_map(fn (float $c): int => (int) round($c), $rgb));
    }

    public static function contrastWithWhite(array $rgb): float
    {
        return 1.05 / (self::luminance($rgb) + 0.05);
    }

    /** @return array{0: float, 1: float, 2: float}|null */
    private static function parse(?string $hex): ?array
    {
        $hex = ltrim(trim((string) $hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }

        return [(float) hexdec(substr($hex, 0, 2)), (float) hexdec(substr($hex, 2, 2)), (float) hexdec(substr($hex, 4, 2))];
    }

    /** @param  array<int, float>  $rgb */
    private static function luminance(array $rgb): float
    {
        [$r, $g, $b] = array_map(function (float $c): float {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, $rgb);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}
