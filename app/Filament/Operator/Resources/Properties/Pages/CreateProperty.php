<?php

namespace App\Filament\Operator\Resources\Properties\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Properties\PropertyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProperty extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = PropertyResource::class;
}
