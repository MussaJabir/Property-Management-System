<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Unit;

/**
 * The three short "highlights" under a room's title on the marketplace room
 * page (phones): where it is, what it has, and how it is let. Built only from
 * the room's own data; a row with nothing to say is left out.
 */
final class RoomHighlights
{
    /** Amenities renters ask about first, in that order. */
    private const PRIORITY = ['water_247', 'backup_power', 'security', 'wifi', 'parking', 'furnished', 'air_conditioning', 'hot_water', 'ensuite', 'cctv'];

    /**
     * @return list<array{icon: string, title: string, text: string}>
     */
    public static function for(Unit $unit): array
    {
        $rows = [];

        $location = $unit->property?->location;
        $where = collect([$location?->name, $location?->district])->filter()->unique()->implode(', ');
        if ($where !== '') {
            $rows[] = ['icon' => 'pin', 'title' => __('marketplace.hl_where', ['area' => $where]), 'text' => __('marketplace.exact_location')];
        }

        $labels = $unit->amenityLabels();
        if ($labels !== []) {
            $keys = array_keys($labels);
            $rank = fn (string $key): int => ($i = array_search($key, self::PRIORITY, true)) === false ? 99 : $i;
            usort($keys, fn (string $a, string $b): int => $rank($a) <=> $rank($b));
            $top = array_slice($keys, 0, 3);

            $rows[] = [
                'icon' => 'amenity:'.$top[0],
                'title' => implode(' · ', array_map(fn (string $key): string => $labels[$key], $top)),
                'text' => __('marketplace.hl_has_text'),
            ];
        }

        $rows[] = [
            'icon' => 'rent',
            'title' => match ($unit->rental_mode) {
                Unit::RENTAL_SHORT_STAY => __('marketplace.hl_nightly'),
                Unit::RENTAL_BOTH => __('marketplace.hl_both'),
                default => __('marketplace.hl_monthly'),
            },
            'text' => __('marketplace.hl_direct'),
        ];

        return $rows;
    }
}
