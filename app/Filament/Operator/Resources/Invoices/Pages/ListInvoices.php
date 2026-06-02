<?php

namespace App\Filament\Operator\Resources\Invoices\Pages;

use App\Filament\Operator\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New invoice'),
        ];
    }

    /**
     * Receivables-focused tabs: Overdue first highlights what needs chasing.
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Invoice::query()->count()),

            'overdue' => Tab::make('Overdue')
                ->badge(Invoice::query()->where('status', Invoice::STATUS_OVERDUE)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', Invoice::STATUS_OVERDUE)),

            'unpaid' => Tab::make('Unpaid')
                ->badge(Invoice::query()->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])),

            'paid' => Tab::make('Paid')
                ->badge(Invoice::query()->where('status', Invoice::STATUS_PAID)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', Invoice::STATUS_PAID)),

            'draft' => Tab::make('Draft')
                ->badge(Invoice::query()->where('status', Invoice::STATUS_DRAFT)->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', Invoice::STATUS_DRAFT)),
        ];
    }
}
