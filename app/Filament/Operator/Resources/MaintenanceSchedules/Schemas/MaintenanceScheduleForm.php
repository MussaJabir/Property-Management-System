<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\MaintenanceSchedules\Schemas;

use App\Models\Contractor;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\Unit;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('What needs doing'))
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->label(__('Job'))
                            ->required()
                            ->maxLength(150)
                            ->placeholder(__('e.g. Service the generator')),

                        Select::make('unit_id')
                            ->label(__('Unit'))
                            ->options(fn (): array => Unit::query()->with('property')->get()
                                ->mapWithKeys(fn (Unit $u): array => [$u->id => $u->property->name.' — '.$u->code])->all())
                            ->required()
                            ->searchable(),

                        Select::make('category')
                            ->label(__('Fault type'))
                            ->options(MaintenanceRequest::CATEGORIES)
                            ->required()
                            ->native(false),

                        Select::make('priority')
                            ->label(__('Priority'))
                            ->options([
                                MaintenanceRequest::PRIORITY_LOW => __('Low'),
                                MaintenanceRequest::PRIORITY_MEDIUM => __('Medium'),
                                MaintenanceRequest::PRIORITY_HIGH => __('High'),
                                MaintenanceRequest::PRIORITY_URGENT => __('Urgent'),
                            ])
                            ->default(MaintenanceRequest::PRIORITY_MEDIUM)
                            ->required()
                            ->native(false),

                        Textarea::make('description')
                            ->label(__('Instructions'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('How often'))
                    ->columns(3)
                    ->components([
                        TextInput::make('every')
                            ->label(__('Every'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->default(1)
                            ->required(),

                        Select::make('period')
                            ->label(__('Period'))
                            ->options(MaintenanceSchedule::PERIODS)
                            ->default('month')
                            ->required()
                            ->native(false),

                        DatePicker::make('next_due_on')
                            ->label(__('Next due'))
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(today())
                            ->required(),
                    ]),

                Section::make(__('Who does it'))
                    ->columns(3)
                    ->components([
                        Select::make('contractor_id')
                            ->label(__('Contractor'))
                            ->options(fn (): array => Contractor::query()->active()->orderBy('name')->get()
                                ->mapWithKeys(fn (Contractor $c): array => [$c->id => $c->displayName()])->all())
                            ->searchable()
                            ->placeholder(__('None')),

                        Select::make('assigned_to_user_id')
                            ->label(__('Staff member'))
                            ->options(fn () => User::query()
                                ->where('tenant_id', tenant('id'))
                                ->where('type', 'operator')
                                ->where('status', 'active')
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->placeholder(__('None')),

                        TextInput::make('estimated_cost')
                            ->label(__('Estimated cost'))
                            ->prefix('TZS')
                            ->numeric()
                            ->minValue(0)
                            ->formatStateUsing(fn ($state) => $state === null ? null : (int) round(((int) $state) / 100))
                            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) round(((float) $state) * 100)),

                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true),
                    ]),
            ]);
    }
}
