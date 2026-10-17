<?php

namespace App\Filament\Operator\Widgets;

use App\Filament\Operator\Resources\Payments\PaymentResource;
use App\Filament\Operator\Widgets\Concerns\RefreshesAfterPayment;
use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Last few recorded payments — the "what just came in?" pane on Today.
 */
class RecentPaymentsWidget extends TableWidget
{
    use RefreshesAfterPayment;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 2];

    /** Money figures are for roles that can see billing. */
    public static function canView(): bool
    {
        return PaymentResource::canViewAny();
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return Payment::query()
            ->with(['invoice.lease.renter', 'invoice.booking.guest'])
            ->latest('payment_date')
            ->latest('id')
            ->limit(5);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Latest payments'))
            ->query($this->getTableQuery())
            ->paginated(false)
            ->columns([
                TextColumn::make('invoice.bill_to_name')
                    ->label(__('Renter'))
                    ->placeholder('—')
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (Payment $p): string => collect([
                        $p->payment_date?->format('d/m/Y'),
                        $p->method_label,
                        $p->status === Payment::STATUS_COMPLETED ? null : __(ucfirst($p->status)),
                    ])->filter()->implode(' · ')),

                TextColumn::make('formatted_amount')
                    ->label(__('Amount'))
                    ->alignEnd()
                    ->weight('semibold')
                    ->color(fn (Payment $p): ?string => $p->status === Payment::STATUS_COMPLETED ? null : 'warning'),
            ])
            ->emptyStateHeading(__('No payments yet'))
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
