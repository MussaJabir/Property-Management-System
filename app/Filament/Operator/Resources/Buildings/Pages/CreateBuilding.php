<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Buildings\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Buildings\BuildingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBuilding extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = BuildingResource::class;
}
