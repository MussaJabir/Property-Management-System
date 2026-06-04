<?php

namespace App\Filament\Operator\Pages\Reports;

use App\Exports\ReportExcelExport;
use App\Models\User;
use App\Reports\Contracts\ReportBuilder;
use App\Services\Reports\ReportPdfGenerator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Common scaffolding for every report page: filter form on top, computed
 * builder, live preview table, and Download PDF / Download Excel header
 * actions. Each concrete page only has to declare its filters and return
 * a configured ReportBuilder.
 */
abstract class BaseReportPage extends Page
{
    protected string $view = 'filament.operator.pages.report';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    /**
     * Filter form state. Each concrete page chooses which keys to use.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->tenant_id) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return $user->can('reports.view');
    }

    public function mount(): void
    {
        $this->form->fill($this->defaultFilters());
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultFilters(): array
    {
        return [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Filament v4 schema for the filter form. Override in each report.
     */
    abstract public function form(Schema $schema): Schema;

    /**
     * Build the report from the current filter state. Override in each report.
     */
    abstract public function builder(): ReportBuilder;

    /**
     * Convenience for the Blade view.
     */
    public function getRows(): Collection
    {
        try {
            return $this->builder()->rows();
        } catch (Throwable $e) {
            return collect();
        }
    }

    /**
     * @return array<int, array{key: string, label: string, align?: string}>
     */
    public function getColumns(): array
    {
        return $this->builder()->columns();
    }

    /**
     * @return array<string, string>
     */
    public function getSummary(): array
    {
        return $this->builder()->summary();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->builder()->meta();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadPdf()),

            Action::make('download_excel')
                ->label('Download Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(fn (): Response => $this->downloadExcel()),
        ];
    }

    protected function downloadPdf(): StreamedResponse
    {
        try {
            $builder = $this->builder();
            $bytes = app(ReportPdfGenerator::class)->render($builder);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not generate PDF')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return response()->streamDownload(fn () => print (''), 'error.txt');
        }

        return response()->streamDownload(
            fn () => print ($bytes),
            $builder->filenameSlug().'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    protected function downloadExcel(): Response
    {
        try {
            $builder = $this->builder();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not generate Excel')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return response()->streamDownload(fn () => print (''), 'error.txt');
        }

        // maatwebsite/excel returns a BinaryFileResponse via download(); we
        // stream it through to the user with the right filename.
        return Excel::download(new ReportExcelExport($builder), $builder->filenameSlug().'.xlsx');
    }
}
