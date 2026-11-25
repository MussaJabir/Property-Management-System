<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Filament\Operator\Resources\Renters\RenterResource;
use App\Models\Renter;
use App\Services\Payments\TenantStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * A renter's statement as a PDF, for staff. An authenticated panel route,
 * so tenancy is active; the renter is looked up here for the same reason as
 * ReceiptPdfController.
 */
class RenterStatementPdfController
{
    public function __invoke(Request $request, string $renter): Response
    {
        $model = Renter::query()->findOrFail($renter);
        abort_unless(RenterResource::canView($model), 403);

        try {
            $from = Carbon::parse((string) $request->query('from', today()->subMonthsNoOverflow(3)->startOfMonth()->toDateString()));
            $to = Carbon::parse((string) $request->query('to', today()->toDateString()));
        } catch (Throwable) {
            abort(422);
        }

        $pdf = app(TenantStatement::class)->pdf($model, $from, $to);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="statement-'.str($model->display_name)->slug().'.pdf"',
        ]);
    }
}
