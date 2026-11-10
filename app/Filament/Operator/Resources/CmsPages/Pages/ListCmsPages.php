<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsPages\Pages;

use App\Filament\Operator\Pages\Website;
use App\Filament\Operator\Resources\CmsPages\CmsPageResource;
use Filament\Resources\Pages\ListRecords;

/** The old pages list; the Website overview replaces it. */
class ListCmsPages extends ListRecords
{
    protected static string $resource = CmsPageResource::class;

    public function mount(): void
    {
        $this->redirect(Website::getUrl());
    }
}
