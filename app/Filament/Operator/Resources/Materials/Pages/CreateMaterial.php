<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Materials\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Materials\MaterialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterial extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = MaterialResource::class;
}
