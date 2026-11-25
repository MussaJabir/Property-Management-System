<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Inspections\Pages;

use App\Filament\Operator\Resources\Inspections\InspectionResource;
use App\Models\Inspection;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Throwable;

class EditInspection extends EditRecord
{
    protected static string $resource = InspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->label(__('Complete'))
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Complete this inspection?'))
                ->modalDescription(__('Save your changes first. The report is locked after this and can no longer be changed.'))
                ->action(function (): void {
                    /** @var Inspection $record */
                    $record = $this->getRecord();
                    try {
                        $record->complete();
                        Notification::make()->title(__('Inspection completed'))->success()->send();
                        $this->redirect(InspectionResource::getUrl('view', ['record' => $record]));
                    } catch (Throwable $e) {
                        Notification::make()->title(__('Could not complete'))->body($e->getMessage())->danger()->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}
