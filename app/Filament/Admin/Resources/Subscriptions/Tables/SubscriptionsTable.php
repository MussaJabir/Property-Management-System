<?php

namespace App\Filament\Admin\Resources\Subscriptions\Tables;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('info'),

                TextColumn::make('plan.formatted_price')
                    ->label('Price')
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'past_due' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('ends_at')
                    ->label('Renews / expires')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->color(fn ($record): string => $record->ends_at && $record->ends_at->isPast() ? 'danger' : 'gray')
                    ->description(fn ($record): ?string => $record->ends_at
                        ? ($record->ends_at->isPast() ? 'Overdue' : 'in '.$record->ends_at->diffForHumans(null, true))
                        : null),

                TextColumn::make('payments_sum_amount_tzs')
                    ->label('Total paid')
                    ->sum('payments', 'amount_tzs')
                    ->formatStateUsing(fn ($state): string => 'TZS '.number_format(((int) $state) / 100, 0, '.', ','))
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'past_due' => 'Past due',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name'),
            ])
            ->recordActions([
                Action::make('recordPayment')
                    ->label('Record payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Amount (TZS)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(fn (Subscription $record): float => round(($record->plan?->price_tzs ?? 0) / 100))
                            ->helperText('Whole shillings.'),

                        DatePicker::make('paid_at')
                            ->label('Paid on')
                            ->required()
                            ->default(now()),

                        Select::make('method')
                            ->required()
                            ->options([
                                'bank_transfer' => 'Bank transfer',
                                'mobile_money' => 'Mobile money',
                                'cash' => 'Cash',
                                'card' => 'Card',
                                'other' => 'Other',
                            ])
                            ->default('bank_transfer')
                            ->native(false),

                        TextInput::make('reference')
                            ->label('Reference / receipt no.')
                            ->maxLength(100),

                        Textarea::make('notes')->rows(2),
                    ])
                    ->action(function (array $data, Subscription $record): void {
                        // Extend from the later of "current period end" or today.
                        $base = ($record->ends_at && $record->ends_at->isFuture())
                            ? $record->ends_at->copy()
                            : Carbon::now();
                        $periodEnd = $base->copy()->addMonths($record->billingMonths());

                        SubscriptionPayment::create([
                            'subscription_id' => $record->id,
                            'tenant_id' => $record->tenant_id,
                            'amount_tzs' => (int) round(((float) $data['amount']) * 100),
                            'paid_at' => $data['paid_at'],
                            'period_start' => $base->toDateString(),
                            'period_end' => $periodEnd->toDateString(),
                            'method' => $data['method'],
                            'reference' => $data['reference'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'recorded_by' => auth('super_admin')->id(),
                        ]);

                        // Extend the paid period and (re)activate.
                        $record->update([
                            'ends_at' => $periodEnd,
                            'status' => 'active',
                        ]);

                        Notification::make()
                            ->title('Payment recorded')
                            ->body('Subscription extended to '.$periodEnd->format('d/m/Y').'.')
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->defaultSort('ends_at', 'asc');
    }
}
