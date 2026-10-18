<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\FeedbackReports\Pages;

use App\Filament\Admin\Resources\FeedbackReports\FeedbackReportResource;
use App\Models\FeedbackReport;
use Filament\Resources\Pages\EditRecord;

class EditFeedbackReport extends EditRecord
{
    protected static string $resource = FeedbackReportResource::class;

    /** Opening a new report marks it as being looked at. */
    protected function afterFill(): void
    {
        /** @var FeedbackReport $report */
        $report = $this->getRecord();

        if ($report->status === FeedbackReport::STATUS_NEW) {
            $report->update(['status' => FeedbackReport::STATUS_IN_PROGRESS]);
            $this->data['status'] = FeedbackReport::STATUS_IN_PROGRESS;
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['resolved_at'] = ($data['status'] ?? null) === FeedbackReport::STATUS_RESOLVED
            ? ($this->getRecord()->getAttribute('resolved_at') ?? now())
            : null;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return FeedbackReportResource::getUrl('index');
    }
}
