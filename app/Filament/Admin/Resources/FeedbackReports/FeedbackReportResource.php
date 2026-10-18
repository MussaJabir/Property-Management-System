<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FeedbackReports;

use App\Filament\Admin\Resources\FeedbackReports\Pages\EditFeedbackReport;
use App\Filament\Admin\Resources\FeedbackReports\Pages\ListFeedbackReports;
use App\Models\FeedbackReport;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Feedback inbox: problems, ideas and questions sent by clients' staff
 * from "Send feedback to BJP". Reply here; the sender sees the reply on
 * their feedback page.
 */
class FeedbackReportResource extends Resource
{
    protected static ?string $model = FeedbackReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static ?string $navigationLabel = 'Feedback';

    protected static ?string $modelLabel = 'Feedback';

    protected static ?string $pluralModelLabel = 'Feedback';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 30;

    public static function getNavigationBadge(): ?string
    {
        $new = FeedbackReport::query()->where('status', FeedbackReport::STATUS_NEW)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('From the client')
                ->columns(2)
                ->components([
                    Placeholder::make('client')
                        ->label('Client')
                        ->content(fn (FeedbackReport $record): string => $record->client !== null ? $record->client->name.' ('.$record->client->slug.')' : '—'),
                    Placeholder::make('from')
                        ->label('Sent by')
                        ->content(fn (FeedbackReport $record): string => $record->user !== null ? $record->user->name.' · '.$record->user->email : '—'),
                    Placeholder::make('kind')
                        ->label('Type')
                        ->content(fn (FeedbackReport $record): string => FeedbackReport::typeLabels()[$record->type] ?? $record->type),
                    Placeholder::make('sent')
                        ->label('Sent')
                        ->content(fn (FeedbackReport $record): string => (string) $record->created_at?->format('d/m/Y H:i')),
                    Placeholder::make('text')
                        ->label('Message')
                        ->content(fn (FeedbackReport $record): HtmlString => new HtmlString(nl2br(e($record->message))))
                        ->columnSpanFull(),
                    Placeholder::make('page')
                        ->label('Page they were on')
                        ->content(fn (FeedbackReport $record): HtmlString|string => $record->page_url !== null
                            ? new HtmlString('<a class="underline" target="_blank" rel="noopener" href="'.e($record->page_url).'">'.e(Str::limit($record->page_url, 90)).'</a>')
                            : '—'),
                    Placeholder::make('screenshot')
                        ->label('Screenshot')
                        ->content(fn (FeedbackReport $record): HtmlString|string => $record->screenshot_path !== null
                            ? new HtmlString('<a class="underline" target="_blank" href="'.e(route('filament.admin.feedback.screenshot', ['report' => $record->getKey()])).'">Open screenshot</a>')
                            : 'None'),
                ]),

            Section::make('Our answer')
                ->components([
                    Select::make('status')
                        ->options(FeedbackReport::statusLabels())
                        ->required()
                        ->native(false),
                    Textarea::make('reply')
                        ->label('Reply to the client')
                        ->helperText('Shown to the sender on their Send feedback page.')
                        ->rows(4)
                        ->maxLength(5000),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Sent')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('client.name')->label('Client')->searchable(),
                TextColumn::make('user.name')->label('From')->placeholder('—'),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FeedbackReport::typeLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => $state === FeedbackReport::TYPE_PROBLEM ? 'danger' : 'info'),
                TextColumn::make('message')->limit(80)->wrap()->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FeedbackReport::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        FeedbackReport::STATUS_NEW => 'warning',
                        FeedbackReport::STATUS_RESOLVED => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(FeedbackReport::statusLabels()),
                SelectFilter::make('type')->options(FeedbackReport::typeLabels()),
            ])
            ->recordActions([EditAction::make()->label('Open')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedbackReports::route('/'),
            'edit' => EditFeedbackReport::route('/{record}/edit'),
        ];
    }
}
