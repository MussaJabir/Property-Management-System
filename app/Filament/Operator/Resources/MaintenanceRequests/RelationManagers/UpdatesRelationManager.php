<?php

namespace App\Filament\Operator\Resources\MaintenanceRequests\RelationManagers;

use App\Models\MaintenanceRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Timeline of progress notes on a maintenance request. Most rows are written
 * automatically by start() / complete() / cancel(); operators can also add
 * free-text notes via the "Add note" header action.
 *
 * Read-only beyond Add Note — the auto-written transition rows must never
 * be edited from the UI.
 */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Timeline';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('note')
                ->required()
                ->rows(3)
                ->placeholder('Add a progress note for the team.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('note')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('status_change')
                    ->label('Status')
                    ->badge()
                    ->placeholder('Note')
                    ->color(fn (?string $state): string => match ($state) {
                        'in_progress' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        null => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => $state ? str_replace('_', ' ', ucfirst($state)) : 'Note'),

                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System')
                    ->toggleable(),

                TextColumn::make('note')
                    ->wrap()
                    ->limit(120),
            ])
            ->filters([
                SelectFilter::make('status_change')
                    ->label('Type')
                    ->options([
                        'in_progress' => 'Started',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->headerActions([
                Action::make('addNote')
                    ->label('Add note')
                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                    ->schema([
                        Textarea::make('note')
                            ->required()
                            ->rows(3)
                            ->placeholder('Progress update visible to the team.'),
                    ])
                    ->action(function (array $data): void {
                        /** @var MaintenanceRequest $request */
                        $request = $this->getOwnerRecord();
                        $request->addNote($data['note'], auth()->id());

                        Notification::make()->title('Note added')->success()->send();
                    }),
            ])
            ->recordActions([
                // Audit rows are immutable.
            ])
            ->toolbarActions([
                // No bulk actions.
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
