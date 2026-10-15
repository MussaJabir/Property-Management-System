<?php

namespace App\Filament\Admin\Resources\Clients\Tables;

use App\Filament\Admin\Resources\Clients\Actions\PlanLimitAllowanceActions;
use App\Filament\Admin\Resources\Clients\Actions\PurgeClientAction;
use App\Filament\Admin\Resources\Clients\Actions\ResendOwnerInviteAction;
use App\Models\Client;
use App\Services\Plans\PlanUsage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('slug')
                    ->label('URL')
                    ->prefix('/')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('URL copied')
                    ->color('gray'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'warning',
                        'suspended' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('unit_usage')
                    ->label('Units')
                    ->state(function (Client $record): string {
                        $usage = PlanUsage::for($record);
                        $limit = $usage->limit(PlanUsage::UNITS);
                        $used = $usage->used(PlanUsage::UNITS);

                        return $limit === null ? (string) $used : $used.' / '.$limit;
                    })
                    ->badge()
                    ->color(function (Client $record): string {
                        $usage = PlanUsage::for($record);

                        return match (true) {
                            $usage->isAtLimit(PlanUsage::UNITS) => 'danger',
                            $usage->isNearLimit(PlanUsage::UNITS) => 'warning',
                            default => 'gray',
                        };
                    })
                    ->description(fn (Client $record): ?string => PlanUsage::for($record)->isWaived()
                        ? __('Limits paused until :date', ['date' => $record->limits_waived_until?->format('d/m/Y')])
                        : null)
                    ->toggleable(),

                TextColumn::make('contact_email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ]),

                Filter::make('near_unit_limit')
                    ->label('Near or at unit limit')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('plan_id')
                        ->whereRaw(
                            '(select count(*) from units where units.tenant_id = tenants.id and units.deleted_at is null)'
                            .' >= cast(? as decimal(4, 2)) * (select max_units from plans where plans.id = tenants.plan_id)',
                            [PlanUsage::WARN_AT],
                        )),

                // Archived clients are reached via the Active/Archived/All tabs
                // on the list page, not a trashed filter.
            ])
            ->recordActions([
                EditAction::make(),

                ResendOwnerInviteAction::make(),

                ActionGroup::make([
                    PlanLimitAllowanceActions::allow(),
                    PlanLimitAllowanceActions::end(),
                ])->label('Plan limits')->icon('heroicon-o-adjustments-horizontal')->color('gray'),

                Action::make('suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'active' || $record->status === 'trial')
                    ->action(fn ($record) => $record->update(['status' => 'suspended'])),

                Action::make('activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'suspended' || $record->status === 'trial')
                    ->action(fn ($record) => $record->update(['status' => 'active'])),

                RestoreAction::make(),

                // Permanent wipe — typed-name confirmation, archived clients only.
                PurgeClientAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Soft archive + restore only. Permanent deletion is a
                    // deliberate, per-client, typed-confirmation purge — never a
                    // bulk one-click action.
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
