<?php

namespace App\Filament\Admin\Resources\Clients\Actions;

use App\Models\Client;
use App\Services\Plans\PlanUsage;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

/**
 * Lets a super admin pause a client's plan limits for a while (staff entering
 * data on site, an upgrade being paid) and end that pause early.
 */
class PlanLimitAllowanceActions
{
    public static function allow(): Action
    {
        return Action::make('allowOverLimit')
            ->label(__('Allow over limit'))
            ->icon('heroicon-o-lock-open')
            ->color('gray')
            ->visible(fn (Client $record): bool => ! $record->trashed() && $record->plan_id !== null && ! PlanUsage::for($record)->isWaived())
            ->modalHeading(__('Allow this client past their plan limits'))
            ->modalDescription(__('They can keep adding properties, units and staff until the date below. Their plan does not change.'))
            ->schema([
                Select::make('days')
                    ->label(__('For'))
                    ->options([7 => __('7 days'), 14 => __('14 days'), 30 => __('30 days')])
                    ->default(14)
                    ->required(),
            ])
            ->action(function (array $data, Client $record): void {
                $record->update(['limits_waived_until' => now()->addDays((int) $data['days'])]);

                Notification::make()
                    ->success()
                    ->title(__('Limits paused until :date', ['date' => $record->limits_waived_until?->format('d/m/Y')]))
                    ->send();
            });
    }

    public static function end(): Action
    {
        return Action::make('endAllowance')
            ->label(__('End limit allowance'))
            ->icon('heroicon-o-lock-closed')
            ->color('gray')
            ->visible(fn (Client $record): bool => PlanUsage::for($record)->isWaived())
            ->requiresConfirmation()
            ->action(function (Client $record): void {
                $record->update(['limits_waived_until' => null]);

                Notification::make()->success()->title(__('Plan limits apply again'))->send();
            });
    }
}
