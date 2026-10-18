<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Ratings\Pages;

use App\Filament\Operator\Resources\Ratings\RatingResource;
use App\Models\Rating;
use Filament\Resources\Pages\ListRecords;

class ListRatings extends ListRecords
{
    protected static string $resource = RatingResource::class;

    /** "Average 4.3 ★ from 12 ratings in the last 90 days" */
    public function getSubheading(): ?string
    {
        $recent = Rating::query()->where('updated_at', '>=', now()->subDays(90));
        $count = (clone $recent)->count();

        if ($count === 0) {
            return null;
        }

        return __('Average :avg ★ from :count in the last 90 days', [
            'avg' => number_format((float) (clone $recent)->avg('score'), 1),
            'count' => trans_choice(':count rating|:count ratings', $count),
        ]);
    }
}
