<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests\Schemas;

use App\Models\MaintenanceRequest;
use App\Models\Unit;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Issue details')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(150)
                            ->placeholder('e.g. Kitchen tap leaking'),

                        Select::make('priority')
                            ->required()
                            ->options([
                                MaintenanceRequest::PRIORITY_LOW => 'Low',
                                MaintenanceRequest::PRIORITY_MEDIUM => 'Medium',
                                MaintenanceRequest::PRIORITY_HIGH => 'High',
                                MaintenanceRequest::PRIORITY_URGENT => 'Urgent',
                            ])
                            ->default(MaintenanceRequest::PRIORITY_MEDIUM)
                            ->native(false),

                        Textarea::make('description')
                            ->required()
                            ->rows(4)
                            ->placeholder('What is the issue? What has been tried?')
                            ->columnSpanFull(),
                    ]),

                Section::make('Location')
                    ->columns(2)
                    ->components([
                        Select::make('unit_id')
                            ->label('Unit')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(fn () => Unit::query()
                                ->with('property')
                                ->get()
                                ->mapWithKeys(function (Unit $unit): array {
                                    $propertyName = $unit->property ? $unit->property->name : 'Unknown property';

                                    return [$unit->id => $propertyName.' — '.$unit->code];
                                })),
                    ]),

                Section::make('Assignment')
                    ->columns(2)
                    ->components([
                        Select::make('assigned_to_user_id')
                            ->label('Assigned to')
                            ->options(fn () => User::query()
                                ->where('type', 'operator')
                                ->where('status', 'active')
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->placeholder('Leave empty — assign later'),
                    ]),

                Section::make('Photos')
                    ->description('Before / during / after photos. Max 5 MB each.')
                    ->components([
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->hiddenLabel()
                            ->collection('photos')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
