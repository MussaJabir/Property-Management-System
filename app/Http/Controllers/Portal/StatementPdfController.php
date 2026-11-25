<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Livewire\Portal\Statement;
use App\Models\Renter;
use App\Services\Payments\TenantStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** The signed-in renter's statement as a PDF: only ever their own. */
class StatementPdfController
{
    public function __invoke(Request $request): Response
    {
        $renter = Auth::guard('renter')->user()?->renter;
        abort_unless($renter instanceof Renter, 404);

        $pdf = app(TenantStatement::class)->pdf(
            $renter,
            Statement::date((string) $request->query('from', ''), today()->subMonthsNoOverflow(3)->startOfMonth()),
            Statement::date((string) $request->query('to', ''), today()),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="statement.pdf"',
        ]);
    }
}
