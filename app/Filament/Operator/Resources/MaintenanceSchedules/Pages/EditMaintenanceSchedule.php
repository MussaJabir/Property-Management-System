<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\MaintenanceSchedules\Pages;

use App\Filament\Operator\Resources\MaintenanceSchedules\MaintenanceScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceSchedule extends EditRecord
{
    protected static string $resource = MaintenanceScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
