<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\Inspections\Schemas;

use App\Models\Inspection;
use App\Models\Lease;
use App\Models\Unit;
use App\Models\User;
use App\Support\Files;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class InspectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Inspection'))
                    ->columns(2)
                    ->components([
                        Select::make('unit_id')
                            ->label(__('Unit'))
                            ->options(fn (): array => Unit::query()->with('property')->get()
                                ->mapWithKeys(fn (Unit $u): array => [$u->id => $u->property->name.' — '.$u->code])->all())
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                // Suggest the unit's current lease.
                                $lease = $state ? Lease::query()->where('unit_id', $state)->where('status', Lease::STATUS_ACTIVE)->latest('start_date')->first() : null;
                                $set('lease_id', $lease?->id);
                            }),

                        Select::make('lease_id')
                            ->label(__('Lease'))
                            ->options(fn (Get $get): array => filled($get('unit_id'))
                                ? Lease::query()->with('renter')->where('unit_id', $get('unit_id'))->latest('start_date')->get()
                                    ->mapWithKeys(fn (Lease $l): array => [$l->id => ($l->renter->full_name ?? '—').' · '.$l->start_date?->format('d/m/Y')])->all()
                                : [])
                            ->placeholder(__('No lease (e.g. an empty unit)')),

                        Select::make('type')
                            ->label(__('Type'))
                            ->options(Inspection::typeOptions())
                            ->default(Inspection::TYPE_MOVE_IN)
                            ->required()
                            ->native(false),

                        DatePicker::make('inspected_on')
                            ->label(__('Date'))
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->default(today())
                            ->required(),

                        Select::make('inspector_user_id')
                            ->label(__('Inspected by'))
                            ->options(fn () => User::query()
                                ->where('tenant_id', tenant('id'))
                                ->where('type', 'operator')
                                ->where('status', 'active')
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->default(fn () => auth()->id()),

                        TextInput::make('renter_present')
                            ->label(__('Tenant present'))
                            ->maxLength(150)
                            ->placeholder(__('Name of the tenant who walked through, if any')),
                    ]),

                Section::make(__('Compared with move-in'))
                    ->description(function (?Inspection $record): string {
                        $worse = $record?->worseThanMoveIn() ?? [];
                        if ($worse === []) {
                            return __('No area is in a worse state than at move-in.');
                        }

                        return collect($worse)->map(fn (array $c, string $area): string => "{$area}: {$c[0]} → {$c[1]}")->implode(' · ');
                    })
                    ->visible(fn (?Inspection $record): bool => $record?->type === Inspection::TYPE_MOVE_OUT && $record->moveInInspection() !== null),

                Section::make(__('Condition of each area'))
                    ->components([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('area')->label(__('Area'))->required()->maxLength(100),
                                Select::make('condition')
                                    ->label(__('Condition'))
                                    ->options(Inspection::CONDITIONS)
                                    ->default('good')
                                    ->required()
                                    ->native(false),
                                TextInput::make('note')->label(__('Note'))->maxLength(255),
                            ])
                            ->columns(3)
                            ->default(fn (): array => array_map(fn (string $area): array => ['area' => $area, 'condition' => 'good', 'note' => null], Inspection::DEFAULT_AREAS))
                            ->addActionLabel(__('Add area'))
                            ->reorderable(false),
                    ]),

                Section::make(__('Meters, keys and notes'))
                    ->columns(3)
                    ->components([
                        TextInput::make('electricity_reading')->label(__('Electricity meter'))->maxLength(30),
                        TextInput::make('water_reading')->label(__('Water meter'))->maxLength(30),
                        TextInput::make('keys_handed')->label(__('Keys handed over'))->numeric()->minValue(0)->maxValue(100),
                        Textarea::make('notes')->label(__('Notes'))->rows(3)->columnSpanFull(),
                    ]),

                Section::make(__('Photos'))
                    ->description(__('Photos of each area as found. Max 5 MB each.'))
                    ->components([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->hiddenLabel()
                            ->collection('photos')
                            ->visibility(Files::usesB2() ? 'private' : null)
                            ->multiple()
                            ->reorderable()
                            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
