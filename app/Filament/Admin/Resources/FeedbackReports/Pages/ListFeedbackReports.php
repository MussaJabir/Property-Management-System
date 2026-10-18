<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FeedbackReports\Pages;

use App\Filament\Admin\Resources\FeedbackReports\FeedbackReportResource;
use Filament\Resources\Pages\ListRecords;

class ListFeedbackReports extends ListRecords
{
    protected static string $resource = FeedbackReportResource::class;
}
