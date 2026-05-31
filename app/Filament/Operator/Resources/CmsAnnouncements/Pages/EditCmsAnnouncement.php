<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsAnnouncements\Pages;

use App\Filament\Operator\Resources\CmsAnnouncements\CmsAnnouncementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCmsAnnouncement extends EditRecord
{
    protected static string $resource = CmsAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
