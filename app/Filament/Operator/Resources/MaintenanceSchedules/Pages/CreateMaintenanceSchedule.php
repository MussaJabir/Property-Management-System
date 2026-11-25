<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\MaintenanceSchedules\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\MaintenanceSchedules\MaintenanceScheduleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceSchedule extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = MaintenanceScheduleResource::class;
}
