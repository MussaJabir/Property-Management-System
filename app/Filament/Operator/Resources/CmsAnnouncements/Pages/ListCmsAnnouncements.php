<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsAnnouncements\Pages;

use App\Filament\Operator\Resources\CmsAnnouncements\CmsAnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCmsAnnouncements extends ListRecords
{
    protected static string $resource = CmsAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New announcement')];
    }
}
