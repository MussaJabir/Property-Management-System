<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsAnnouncements\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\CmsAnnouncements\CmsAnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCmsAnnouncement extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = CmsAnnouncementResource::class;
}
