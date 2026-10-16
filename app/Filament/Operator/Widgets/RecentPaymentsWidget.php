<?php

namespace App\Filament\Operator\Widgets;

use App\Filament\Operator\Resources\Payments\PaymentResource;
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
            ->with(['invoice.lease.renter'])
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
                TextColumn::make('invoice.lease.renter.display_name')
                    ->label(__('Renter'))
                    ->placeholder('—')
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (Payment $p): string => collect([
                        $p->payment_date?->format('d/m/Y'),
                        self::methodLabel($p),
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

    private static function methodLabel(Payment $payment): string
    {
        if ($payment->method === Payment::METHOD_MOBILE_MONEY) {
            return match ($payment->mobile_money_provider) {
                Payment::PROVIDER_MPESA => 'M-Pesa',
                Payment::PROVIDER_TIGOPESA => 'Tigo Pesa',
                Payment::PROVIDER_AIRTELMONEY => 'Airtel Money',
                Payment::PROVIDER_HALOPESA => 'Halo Pesa',
                default => __('Mobile money'),
            };
        }

        return __(str_replace('_', ' ', ucfirst($payment->method ?? 'unknown')));
    }
}
