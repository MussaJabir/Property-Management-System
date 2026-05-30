<?php

namespace App\Reports\Contracts;

use Illuminate\Support\Collection;

/**
 * Common shape every report (Monthly Rent Collection, Outstanding Rent,
 * Occupancy, …) implements. Lets us share one PDF template + one Excel
 * exporter across every report, with the report-specific data living in
 * a single small class per report.
 *
 * Contract:
 *   meta()     → header strings (title, subtitle, period, client name, …)
 *   columns()  → column definitions for both PDF and Excel rendering
 *   rows()     → the rendered, pre-formatted rows (each row keyed by column key)
 *   summary()  → footer totals — list of label → value pairs
 */
interface ReportBuilder
{
    /**
     * @return array{title: string, subtitle?: string, period?: string, client?: string, generated_at?: string}
     */
    public function meta(): array;

    /**
     * Each column: ['key' => 'foo', 'label' => 'Foo', 'align' => 'left'|'right']
     *
     * @return array<int, array{key: string, label: string, align?: string}>
     */
    public function columns(): array;

    /**
     * Rows as assoc arrays keyed by column key, values already formatted for display.
     *
     * @return Collection<int, array<string, string>>
     */
    public function rows(): Collection;

    /**
     * Footer totals — label → value (both pre-formatted strings).
     *
     * @return array<string, string>
     */
    public function summary(): array;

    /**
     * File slug used when downloading (PDF or Excel). Lower-case, dashes only.
     */
    public function filenameSlug(): string;
}
