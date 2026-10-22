<?php

namespace App\Filament\Operator\Resources\Invoices\Pages;

use App\Filament\Operator\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Only drafts can be changed. Once issued, an invoice opens read-only so
 * amounts are not changed by accident; it is corrected by cancelling it
 * (from the list) and issuing a new one.
 */
class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->disabled(! $this->isEditable());
    }

    public function getTitle(): string|Htmlable
    {
        return $this->isEditable() ? parent::getTitle() : $this->getRecordTitle();
    }

    public function getBreadcrumb(): string
    {
        return $this->isEditable() ? parent::getBreadcrumb() : __('View');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn (): bool => $this->isEditable()),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return $this->isEditable() ? parent::getFormActions() : [$this->getCancelFormAction()->label(__('Back'))];
    }

    protected function beforeValidate(): void
    {
        if (! $this->isEditable()) {
            throw new Halt;
        }
    }

    private function isEditable(): bool
    {
        $record = $this->getRecord();

        return $record instanceof Invoice && $record->isDraft();
    }
}
