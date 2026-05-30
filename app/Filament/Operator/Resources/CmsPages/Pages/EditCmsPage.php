<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsPages\Pages;

use App\Filament\Operator\Resources\CmsPages\CmsPageResource;
use Filament\Resources\Pages\EditRecord;

class EditCmsPage extends EditRecord
{
    protected static string $resource = CmsPageResource::class;
}
