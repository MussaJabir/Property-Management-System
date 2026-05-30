<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsAnnouncements;

use App\Filament\Operator\Resources\CmsAnnouncements\Pages\CreateCmsAnnouncement;
use App\Filament\Operator\Resources\CmsAnnouncements\Pages\EditCmsAnnouncement;
use App\Filament\Operator\Resources\CmsAnnouncements\Pages\ListCmsAnnouncements;
use App\Models\CmsAnnouncement;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CmsAnnouncementResource extends Resource
{
    protected static ?string $model = CmsAnnouncement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Announcements';

    protected static ?string $modelLabel = 'Announcement';

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('title')->required()->maxLength(200),
                Textarea::make('excerpt')->rows(2)->maxLength(500)->helperText('A one-line summary shown on the News index.'),
                Textarea::make('body')->rows(10)->required(),
                DateTimePicker::make('published_at')->label('Publish at')->helperText('Leave blank to keep as draft.'),
            ])->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->limit(60),
                IconColumn::make('published_at')
                    ->label('Published')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->published_at !== null && $record->published_at->isPast()),
                TextColumn::make('published_at')->label('Publish date')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Updated')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCmsAnnouncements::route('/'),
            'create' => CreateCmsAnnouncement::route('/create'),
            'edit' => EditCmsAnnouncement::route('/{record}/edit'),
        ];
    }
}
