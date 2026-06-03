<?php

namespace App\Filament\Admin\Resources\Subscriptions\Schemas;

use App\Models\Client;
use App\Models\Plan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscription')
                    ->columns(2)
                    ->components([
                        Select::make('tenant_id')
                            ->label('Client')
                            ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),

                        Select::make('plan_id')
                            ->label('Plan')
                            ->options(fn () => Plan::query()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->native(false),

                        Select::make('status')
                            ->required()
                            ->options([
                                'active' => 'Active',
                                'past_due' => 'Past due',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('active')
                            ->native(false),

                        DatePicker::make('started_at')
                            ->label('Started on')
                            ->required()
                            ->default(now()),

                        DatePicker::make('ends_at')
                            ->label('Renews / expires on')
                            ->helperText('When the current paid period ends. Recording a payment extends this.'),
                    ]),
            ]);
    }
}
