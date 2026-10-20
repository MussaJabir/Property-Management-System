<?php

declare(strict_types=1);

use App\Support\BrandColor;

it('keeps a dark brand colour as it is', function () {
    expect(BrandColor::readable('#0F766E'))->toBe('#0F766E');
});

it('darkens a neon brand colour until white text on it is readable', function () {
    $color = BrandColor::readable('#00ff63');

    expect($color)->not->toBe('#00FF63')
        ->and(BrandColor::contrastWithWhite(array_map(fn ($h) => (float) hexdec($h), str_split(ltrim($color, '#'), 2))))
        ->toBeGreaterThanOrEqual(4.5);
});

it('falls back when no usable colour is set', function () {
    expect(BrandColor::readable(null))->toBe(BrandColor::FALLBACK)
        ->and(BrandColor::readable('not-a-colour'))->toBe(BrandColor::FALLBACK);
});
