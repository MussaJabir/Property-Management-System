<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Models\Receipt;
use App\Services\ReceiptPdfGenerator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Receipt PDF for operators (the "Download receipt" button after recording a
 * payment). Registered as an authenticated panel route, so tenancy is active
 * by the time this runs; the receipt is looked up here rather than through
 * route-model binding, which runs before tenancy and would skip the scope.
 */
class ReceiptPdfController
{
    public function __invoke(string $receipt): StreamedResponse
    {
        $model = Receipt::query()->findOrFail($receipt);

        Gate::authorize('view', $model);

        // Same as the renter portal: render once, then serve the stored copy.
        $disk = Storage::disk(config('filesystems.default'));

        if (! $model->pdf_path || ! $disk->exists($model->pdf_path)) {
            app(ReceiptPdfGenerator::class)->store($model);
            $model->refresh();
        }

        return $disk->download((string) $model->pdf_path, 'receipt-'.$model->receipt_number.'.pdf');
    }
}
