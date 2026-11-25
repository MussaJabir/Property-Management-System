<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Inspections\Pages;

use App\Filament\Operator\Resources\Inspections\InspectionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInspection extends ViewRecord
{
    protected static string $resource = InspectionResource::class;
}
