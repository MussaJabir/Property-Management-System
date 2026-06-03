<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Client;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The most recently onboarded clients — the "who just joined" pane on the
 * super-admin dashboard. Central context, so no tenant scoping.
 */
class RecentClientsWidget extends TableWidget
{
    protected static ?string $heading = 'Recently added clients';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder|Relation|null
    {
        return Client::query()
            ->with('plan')
            ->latest('created_at')
            ->limit(8);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->label('Client')
                    ->weight('semibold')
                    ->description(fn (Client $record): ?string => $record->slug ? '/'.$record->slug : null),

                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->badge()
                    ->color('info')
                    ->placeholder('No plan'),

                TextColumn::make('contact_email')
                    ->label('Contact')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        'inactive' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date('d/m/Y'),
            ])
            ->emptyStateIcon('heroicon-o-building-office-2')
            ->emptyStateHeading('No clients yet')
            ->emptyStateDescription('Create your first client workspace from the Clients section.');
    }
}
