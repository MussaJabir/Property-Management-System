<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\ContactSubmissions\Pages;

use App\Filament\Operator\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Models\ContactSubmission;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContactSubmission extends EditRecord
{
    protected static string $resource = ContactSubmissionResource::class;

    /**
     * Mark a 'new' submission as 'read' the first time an operator opens it,
     * so the badge count clears without manual ceremony.
     */
    protected function afterFill(): void
    {
        if ($this->record->status === ContactSubmission::STATUS_NEW) {
            $this->record->forceFill(['status' => ContactSubmission::STATUS_READ])->save();
            $this->data['status'] = ContactSubmission::STATUS_READ;
        }
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
