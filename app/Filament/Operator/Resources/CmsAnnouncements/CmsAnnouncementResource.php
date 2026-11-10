<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsAnnouncements;

use App\Filament\Operator\Navigation\NavGroup;
use App\Filament\Operator\Resources\CmsAnnouncements\Pages\CreateCmsAnnouncement;
use App\Filament\Operator\Resources\CmsAnnouncements\Pages\EditCmsAnnouncement;
use App\Filament\Operator\Resources\CmsAnnouncements\Pages\ListCmsAnnouncements;
use App\Models\CmsAnnouncement;
use App\Support\Files;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class CmsAnnouncementResource extends Resource
{
    protected static ?string $model = CmsAnnouncement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'News';

    protected static ?string $modelLabel = 'news post';

    protected static string|\UnitEnum|null $navigationGroup = NavGroup::Website;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'lg' => 3])->schema([
                Section::make()
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        TextInput::make('title')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(200)
                            ->live(onBlur: true),
                        FileUpload::make('image_path')
                            ->label(__('Photo'))
                            ->helperText(__('Optional. Shown at the top of the post.'))
                            ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageEditor()
                            ->disk(Files::publicDisk())
                            ->visibility('public')
                            ->directory('cms/news')
                            ->maxSize(8192)
                            ->live(),
                        RichEditor::make('body')
                            ->label(__('Text'))
                            ->required()
                            ->toolbarButtons([['bold', 'italic'], ['bulletList', 'link']])
                            ->helperText(__('The first line becomes the short summary on the News page.'))
                            ->live(onBlur: true),
                    ]),
                Grid::make(1)
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Section::make()->schema([
                            Toggle::make('show')
                                ->label(__('Show on website'))
                                ->helperText(fn (Get $get): string => $get('later') ? __('It appears on the date you pick.') : __('Visitors see it right away.'))
                                ->default(true)
                                ->live(),
                            Toggle::make('later')
                                ->label(__('Pick a later date instead'))
                                ->visible(fn (Get $get): bool => (bool) $get('show'))
                                ->live(),
                            DateTimePicker::make('published_at')
                                ->label(__('Show it from'))
                                ->native(false)
                                ->displayFormat('d/m/Y H:i')
                                ->seconds(false)
                                ->visible(fn (Get $get): bool => $get('show') && $get('later'))
                                ->required(fn (Get $get): bool => $get('show') && $get('later'))
                                ->after('now'),
                        ]),
                        Section::make(__('How it looks on your site'))->schema([
                            View::make('filament.operator.website.news-preview')
                                ->viewData(fn (Get $get): array => [
                                    'title' => $get('title'),
                                    'photo' => self::previewUrl($get('image_path')),
                                ]),
                        ]),
                    ]),
            ]),
        ])->columns(1);
    }

    /**
     * Form values for a post: the publish date becomes the switches.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillPublishing(array $data): array
    {
        $date = isset($data['published_at']) ? Carbon::parse($data['published_at']) : null;

        return [...$data, 'show' => $date !== null, 'later' => $date?->isFuture() ?? false];
    }

    /**
     * The publish date from the switches: none when hidden, the picked date
     * when later, otherwise now (or the date it already went out).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function savePublishing(array $data, ?CmsAnnouncement $post = null): array
    {
        $alreadyOut = $post?->published_at !== null && $post->published_at->isPast();

        $data['published_at'] = match (true) {
            empty($data['show']) => null,
            ! empty($data['later']) => $data['published_at'] ?? null,
            default => $alreadyOut ? $post->published_at : now(),
        };
        unset($data['show'], $data['later']);

        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk(Files::publicDisk())
                    ->square()
                    ->size(44),
                TextColumn::make('title')->label(__('Title'))->searchable()->sortable()->limit(60),
                TextColumn::make('status')
                    ->label(__('On website'))
                    ->badge()
                    ->state(fn (CmsAnnouncement $record): string => match ($record->state()) {
                        'shown' => __('Showing'),
                        'scheduled' => __('From :date', ['date' => $record->published_at?->format('d/m/Y H:i')]),
                        default => __('Hidden'),
                    })
                    ->color(fn (CmsAnnouncement $record): string => match ($record->state()) {
                        'shown' => 'success',
                        'scheduled' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('updated_at')->label(__('Updated'))->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([EditAction::make()]);
    }

    private static function previewUrl(mixed $state): ?string
    {
        $file = is_array($state) ? collect($state)->first() : $state;

        try {
            return match (true) {
                $file instanceof TemporaryUploadedFile => $file->isPreviewable() ? $file->temporaryUrl() : null,
                is_string($file) && $file !== '' => Files::publicUrl($file),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
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
