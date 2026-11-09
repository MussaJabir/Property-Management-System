<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\BookingRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * The short link in the SMS alert (/r/8f3k1a2b): opens that request in the
 * dashboard. Signed out: log in first, then land on it. Only the staff of
 * the workspace the request belongs to can open it.
 */
class OpenRequestController extends Controller
{
    public function __invoke(string $code): RedirectResponse
    {
        $user = auth('web')->user();

        if (! $user instanceof User) {
            return redirect()->guest(route('filament.operator.auth.login'));
        }

        abort_unless($user->type === User::TYPE_OPERATOR && $user->tenant_id !== null, 404);

        $request = BookingRequest::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereNull('deleted_at')
            ->whereRaw('CAST(id AS TEXT) LIKE ?', [$code.'%'])
            ->first();

        abort_if($request === null, 404);

        return redirect()->route('filament.operator.resources.booking-requests.view', ['record' => $request->getKey()]);
    }
}
