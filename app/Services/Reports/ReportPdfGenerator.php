<?php

namespace App\Services\Reports;

use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * Renders any ReportBuilder to PDF using a shape-adaptive Blade template.
 *
 * One generator + one Blade view powers all seven v1 reports — the shape
 * comes from the builder's columns() + rows() + summary() output.
 */
class ReportPdfGenerator
{
    public function render(ReportBuilder $builder): string
    {
        $html = View::make('reports.template', [
            'builder' => $builder,
            'meta' => $builder->meta(),
            'columns' => $builder->columns(),
            'rows' => $builder->rows(),
            'summary' => $builder->summary(),
        ])->render();

        return Browsershot::html($html)
            ->format('A4')
            ->landscape($this->isWide($builder))
            ->noSandbox()
            ->showBackground()
            ->pdf();
    }

    /**
     * Heuristic: more than 5 columns reads better in landscape.
     */
    protected function isWide(ReportBuilder $builder): bool
    {
        return count($builder->columns()) > 5;
    }
}
