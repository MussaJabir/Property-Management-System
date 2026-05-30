<?php

namespace App\Reports\Builders;

use App\Models\Client;
use App\Models\Expense;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Expense report — every expense in the date range, grouped by category +
 * property in the summary. Rows are itemised; totals appear in the footer.
 */
class ExpenseReport implements ReportBuilder
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public ?string $propertyId = null,
    ) {}

    public function meta(): array
    {
        return [
            'title' => 'Expense Report',
            'period' => $this->from->format('d/m/Y').' → '.$this->to->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'property', 'label' => 'Property'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'amount', 'label' => 'Amount (TZS)', 'align' => 'right'],
        ];
    }

    public function rows(): Collection
    {
        $query = Expense::query()
            ->with(['category', 'property'])
            ->whereBetween('expense_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->orderBy('expense_date');

        if ($this->propertyId) {
            $query->where('property_id', $this->propertyId);
        }

        return $query->get()->map(fn (Expense $exp): array => [
            'date' => $exp->expense_date->format('d/m/Y'),
            'category' => $exp->category?->name ?? '—',
            'property' => $exp->property?->name ?? 'General overhead',
            'description' => mb_strimwidth((string) $exp->description, 0, 60, '…'),
            'amount' => number_format($exp->amount / 100, 0, '.', ','),
        ]);
    }

    public function summary(): array
    {
        $query = Expense::query()
            ->whereBetween('expense_date', [$this->from->toDateString(), $this->to->toDateString()]);

        if ($this->propertyId) {
            $query->where('property_id', $this->propertyId);
        }

        $rows = $query->with('category')->get();
        $perCategory = $rows->groupBy(fn (Expense $e) => $e->category?->name ?? 'Uncategorised')
            ->map(fn ($group) => (int) $group->sum('amount'))
            ->sortDesc();

        $summary = [];
        foreach ($perCategory as $name => $cents) {
            $summary[$name] = 'TZS '.number_format($cents / 100, 0, '.', ',');
        }

        $summary['Total expenses'] = 'TZS '.number_format($rows->sum('amount') / 100, 0, '.', ',');

        return $summary;
    }

    public function filenameSlug(): string
    {
        return 'expenses-'.$this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }
}
