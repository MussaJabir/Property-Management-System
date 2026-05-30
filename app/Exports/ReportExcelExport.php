<?php

namespace App\Exports;

use App\Reports\Contracts\ReportBuilder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Wraps any ReportBuilder as an Excel export. One exporter class powers all
 * v1 reports — the shape comes from the builder's columns() + rows().
 *
 * Title row + header row + data rows + a blank row + summary rows.
 */
class ReportExcelExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    public function __construct(public ReportBuilder $builder) {}

    public function title(): string
    {
        $title = $this->builder->meta()['title'] ?? 'Report';

        // Excel limits sheet names to 31 chars and disallows certain chars.
        return mb_substr(preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '', $title), 0, 31);
    }

    public function headings(): array
    {
        return array_map(fn (array $col): string => $col['label'], $this->builder->columns());
    }

    /**
     * @return Collection<int, array<int, string>>
     */
    public function collection(): Collection
    {
        $columns = $this->builder->columns();

        return $this->builder->rows()->map(function (array $row) use ($columns): array {
            $ordered = [];
            foreach ($columns as $col) {
                $ordered[] = $row[$col['key']] ?? '';
            }

            return $ordered;
        });
    }

    public function styles(Worksheet $sheet): array
    {
        $headerRowIndex = 1;

        // Style the header row.
        $sheet->getStyle("A{$headerRowIndex}:".$sheet->getHighestColumn().$headerRowIndex)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // Append summary rows at the bottom so they sit beneath the data
        // without complicating the FromCollection contract.
        $summary = $this->builder->summary();
        if (! empty($summary)) {
            $lastRow = $sheet->getHighestRow();
            $startRow = $lastRow + 2;

            foreach ($summary as $label => $value) {
                $sheet->setCellValue("A{$startRow}", $label);
                $sheet->setCellValue("B{$startRow}", $value);
                $sheet->getStyle("A{$startRow}:B{$startRow}")->getFont()->setBold(true);
                $startRow++;
            }
        }

        return [];
    }
}
