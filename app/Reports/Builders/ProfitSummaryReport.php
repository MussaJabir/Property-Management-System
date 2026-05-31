<?php

namespace App\Reports\Builders;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Property;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Profit Summary — income (completed payments) minus expenses, broken down
 * per property over the date range. Income for a payment is attributed to
 * the property of the unit on its invoice's lease.
 *
 * "General overhead" expenses (property_id null) are not attributed to a
 * specific property — they show as a single row at the bottom and reduce
 * the portfolio-wide profit on the summary line.
 */
class ProfitSummaryReport implements ReportBuilder
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
    ) {}

    public function meta(): array
    {
        return [
            'title' => 'Profit Summary',
            'period' => $this->from->format('d/m/Y').' → '.$this->to->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'property', 'label' => 'Property'],
            ['key' => 'income', 'label' => 'Income (TZS)', 'align' => 'right'],
            ['key' => 'expenses', 'label' => 'Expenses (TZS)', 'align' => 'right'],
            ['key' => 'profit', 'label' => 'Profit (TZS)', 'align' => 'right'],
        ];
    }

    public function rows(): Collection
    {
        $from = $this->from->toDateString();
        $to = $this->to->toDateString();

        $properties = Property::query()
            ->with(['units.leases.invoices.payments' => fn ($q) => $q
                ->where('status', Payment::STATUS_COMPLETED)
                ->whereBetween('payment_date', [$from, $to])])
            ->orderBy('name')
            ->get();

        $rows = $properties->map(function (Property $property) use ($from, $to): array {
            $income = $property->units
                ->flatMap(fn ($u) => $u->leases->flatMap(fn ($l) => $l->invoices->flatMap->payments))
                ->sum('amount');

            $expenses = (int) Expense::query()
                ->where('property_id', $property->id)
                ->whereBetween('expense_date', [$from, $to])
                ->sum('amount');

            return [
                'property' => $property->name,
                'income' => number_format($income / 100, 0, '.', ','),
                'expenses' => number_format($expenses / 100, 0, '.', ','),
                'profit' => number_format(($income - $expenses) / 100, 0, '.', ','),
            ];
        });

        $generalOverhead = (int) Expense::query()
            ->whereNull('property_id')
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        if ($generalOverhead > 0) {
            $rows->push([
                'property' => '— General overhead —',
                'income' => '0',
                'expenses' => number_format($generalOverhead / 100, 0, '.', ','),
                'profit' => '-'.number_format($generalOverhead / 100, 0, '.', ','),
            ]);
        }

        return $rows;
    }

    public function summary(): array
    {
        $from = $this->from->toDateString();
        $to = $this->to->toDateString();

        $income = (int) Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');

        $expenses = (int) Expense::query()
            ->whereBetween('expense_date', [$from, $to])
            ->sum('amount');

        return [
            'Total income' => 'TZS '.number_format($income / 100, 0, '.', ','),
            'Total expenses' => 'TZS '.number_format($expenses / 100, 0, '.', ','),
            'Net profit' => 'TZS '.number_format(($income - $expenses) / 100, 0, '.', ','),
        ];
    }

    public function filenameSlug(): string
    {
        return 'profit-summary-'.$this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }
}
