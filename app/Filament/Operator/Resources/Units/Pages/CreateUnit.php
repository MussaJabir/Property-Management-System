<?php

namespace App\Filament\Operator\Resources\Units\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Units\UnitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUnit extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = UnitResource::class;
}
