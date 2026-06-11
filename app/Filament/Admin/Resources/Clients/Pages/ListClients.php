<?php

namespace App\Filament\Admin\Resources\Clients\Pages;

use App\Filament\Admin\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Tabs above the table so archived clients are one click away — no need to
     * hunt through the Trashed filter dropdown. "Archived" is where the Purge
     * action lives. Counts are shown as badges.
     */
    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withoutTrashed()) // @phpstan-ignore method.notFound
                ->badge(Client::query()->count()),

            'archived' => Tab::make('Archived')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed()) // @phpstan-ignore method.notFound
                ->badge(Client::onlyTrashed()->count())
                ->badgeColor('danger'),

            'all' => Tab::make('All')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withTrashed()) // @phpstan-ignore method.notFound
                ->badge(Client::withTrashed()->count()),
        ];
    }
}
