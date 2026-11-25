<?php

declare(strict_types=1);

namespace App\Reports\Builders;

use App\Models\Client;
use App\Models\MaintenanceRequest;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Maintenance spend: every job finished in the period with its estimate,
 * labour, materials and actual cost, totalled by property in the footer.
 */
class MaintenanceCostReport implements ReportBuilder
{
    public function __construct(
        public Carbon $from,
        public Carbon $to,
        public ?string $propertyId = null,
        public ?string $category = null,
    ) {}

    public function meta(): array
    {
        return [
            'title' => 'Maintenance Cost Report',
            'period' => $this->from->format('d/m/Y').' → '.$this->to->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'completed', 'label' => 'Completed'],
            ['key' => 'work_order', 'label' => 'Work order'],
            ['key' => 'unit', 'label' => 'Property / unit'],
            ['key' => 'job', 'label' => 'Job'],
            ['key' => 'type', 'label' => 'Fault type'],
            ['key' => 'done_by', 'label' => 'Done by'],
            ['key' => 'estimate', 'label' => 'Estimate', 'align' => 'right'],
            ['key' => 'labour', 'label' => 'Labour', 'align' => 'right'],
            ['key' => 'materials', 'label' => 'Materials', 'align' => 'right'],
            ['key' => 'actual', 'label' => 'Actual', 'align' => 'right'],
            ['key' => 'variance', 'label' => 'Over / under', 'align' => 'right'],
        ];
    }

    public function rows(): Collection
    {
        return $this->jobs()->map(fn (MaintenanceRequest $r): array => [
            'completed' => $r->completed_at?->format('d/m/Y') ?? '—',
            'work_order' => $r->work_order_number ?? '—',
            'unit' => ($r->unit?->property?->name ?? '—').' / '.($r->unit?->code ?? '—'),
            'job' => mb_strimwidth($r->title, 0, 50, '…'),
            'type' => $r->categoryLabel(),
            'done_by' => $r->contractor?->displayName() ?? $r->assignedTo?->name ?? '—',
            'estimate' => self::amount($r->estimated_cost),
            'labour' => self::amount($r->labour_cost),
            'materials' => self::amount($r->materials_cost),
            'actual' => self::amount($r->cost),
            'variance' => self::variance($r->costVariance()),
        ]);
    }

    public function summary(): array
    {
        $jobs = $this->jobs();
        $summary = [];

        $perProperty = $jobs->groupBy(fn (MaintenanceRequest $r): string => $r->unit?->property?->name ?? '—')
            ->map(fn (Collection $group): int => (int) $group->sum('cost'))
            ->sortDesc();
        foreach ($perProperty as $name => $cents) {
            $summary[(string) $name] = self::money($cents);
        }

        $summary['Jobs completed'] = (string) $jobs->count();
        $summary['Over estimate'] = (string) $jobs->filter(fn (MaintenanceRequest $r): bool => ($r->costVariance() ?? 0) > 0)->count();
        $summary['Labour'] = self::money((int) $jobs->sum('labour_cost'));
        $summary['Materials'] = self::money((int) $jobs->sum('materials_cost'));
        $summary['Total maintenance cost'] = self::money((int) $jobs->sum('cost'));

        return $summary;
    }

    public function filenameSlug(): string
    {
        return 'maintenance-costs-'.$this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }

    /** @return Collection<int, MaintenanceRequest> */
    private function jobs(): Collection
    {
        return MaintenanceRequest::query()
            ->with(['unit.property', 'contractor', 'assignedTo'])
            ->where('status', MaintenanceRequest::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$this->from->copy()->startOfDay(), $this->to->copy()->endOfDay()])
            ->when($this->propertyId, fn (Builder $q) => $q->whereHas('unit', fn (Builder $u) => $u->where('property_id', $this->propertyId)))
            ->when($this->category, fn (Builder $q) => $q->where('category', $this->category))
            ->orderBy('completed_at')
            ->get();
    }

    private static function amount(?int $cents): string
    {
        return $cents === null ? '—' : number_format($cents / 100, 0, '.', ',');
    }

    private static function variance(?int $cents): string
    {
        if ($cents === null) {
            return '—';
        }

        return ($cents > 0 ? '+' : '').number_format($cents / 100, 0, '.', ',');
    }

    private static function money(int $cents): string
    {
        return 'TZS '.number_format($cents / 100, 0, '.', ',');
    }
}
