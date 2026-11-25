<?php

declare(strict_types=1);

namespace App\Reports\Builders;

use App\Models\Client;
use App\Models\MaintenanceRequest;
use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How maintenance is going, by who does the work (contractor or staff):
 * jobs finished, on time or late, days taken, jobs still open past their due
 * date, repeat faults and cost.
 */
class MaintenancePerformanceReport implements ReportBuilder
{
    /** @var list<array<string, string>>|null */
    private ?array $cache = null;

    public function __construct(
        public Carbon $from,
        public Carbon $to,
    ) {}

    public function meta(): array
    {
        return [
            'title' => 'Maintenance Performance',
            'period' => $this->from->format('d/m/Y').' → '.$this->to->format('d/m/Y'),
            'client' => tenant('name') ?? optional(Client::find(tenant('id')))->name ?? 'PMS',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'who', 'label' => 'Done by'],
            ['key' => 'finished', 'label' => 'Finished', 'align' => 'right'],
            ['key' => 'on_time', 'label' => 'On time', 'align' => 'right'],
            ['key' => 'late', 'label' => 'Late', 'align' => 'right'],
            ['key' => 'on_time_rate', 'label' => 'On time %', 'align' => 'right'],
            ['key' => 'avg_days', 'label' => 'Avg days', 'align' => 'right'],
            ['key' => 'overdue_open', 'label' => 'Open & overdue', 'align' => 'right'],
            ['key' => 'repeats', 'label' => 'Repeat faults', 'align' => 'right'],
            ['key' => 'cost', 'label' => 'Cost (TZS)', 'align' => 'right'],
        ];
    }

    public function rows(): Collection
    {
        return collect($this->build());
    }

    public function summary(): array
    {
        $finished = $this->finishedJobs();
        $onTime = $finished->filter(fn (MaintenanceRequest $r): bool => $r->wasOnTime() === true)->count();

        return [
            'Jobs finished' => (string) $finished->count(),
            'Finished on time' => $finished->isEmpty() ? '—' : (string) round($onTime / $finished->count() * 100).'%',
            'Open and overdue now' => (string) MaintenanceRequest::query()->overdue()->count(),
            'Repeat faults' => (string) $finished->filter(fn (MaintenanceRequest $r): bool => $r->isRepeat())->count(),
            'Total cost' => 'TZS '.number_format(((int) $finished->sum('cost')) / 100, 0, '.', ','),
        ];
    }

    public function filenameSlug(): string
    {
        return 'maintenance-performance-'.$this->from->format('Ymd').'-'.$this->to->format('Ymd');
    }

    /** @return list<array<string, string>> */
    private function build(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $finished = $this->finishedJobs()->groupBy(fn (MaintenanceRequest $r): string => self::who($r));
        $overdue = MaintenanceRequest::query()->overdue()->with(['contractor', 'assignedTo'])->get()
            ->groupBy(fn (MaintenanceRequest $r): string => self::who($r));

        $rows = [];
        foreach ($finished->keys()->merge($overdue->keys())->unique()->sort()->values() as $who) {
            /** @var Collection<int, MaintenanceRequest> $jobs */
            $jobs = $finished->get($who, collect());
            $onTime = $jobs->filter(fn (MaintenanceRequest $r): bool => $r->wasOnTime() === true)->count();
            $days = $jobs->map(fn (MaintenanceRequest $r): float => (float) $r->reported_at->diffInDays($r->completed_at ?? $r->reported_at));

            $rows[] = [
                'who' => (string) $who,
                'finished' => (string) $jobs->count(),
                'on_time' => (string) $onTime,
                'late' => (string) ($jobs->count() - $onTime),
                'on_time_rate' => $jobs->isEmpty() ? '—' : round($onTime / $jobs->count() * 100).'%',
                'avg_days' => $jobs->isEmpty() ? '—' : number_format((float) $days->avg(), 1),
                'overdue_open' => (string) $overdue->get($who, collect())->count(),
                'repeats' => (string) $jobs->filter(fn (MaintenanceRequest $r): bool => $r->isRepeat())->count(),
                'cost' => number_format(((int) $jobs->sum('cost')) / 100, 0, '.', ','),
            ];
        }

        return $this->cache = $rows;
    }

    /** @return Collection<int, MaintenanceRequest> */
    private function finishedJobs(): Collection
    {
        return MaintenanceRequest::query()
            ->with(['contractor', 'assignedTo'])
            ->where('status', MaintenanceRequest::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$this->from->copy()->startOfDay(), $this->to->copy()->endOfDay()])
            ->get();
    }

    private static function who(MaintenanceRequest $r): string
    {
        return $r->contractor?->displayName() ?? $r->assignedTo?->name ?? __('Not assigned');
    }
}
