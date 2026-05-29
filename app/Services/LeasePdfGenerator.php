<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lease;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * Renders the lease contract Blade template to a PDF via Browsershot
 * (headless Chromium under the hood).
 *
 * Usage:
 *   $bytes = app(LeasePdfGenerator::class)->render($lease);   // raw PDF
 *   $media = app(LeasePdfGenerator::class)->store($lease);    // saved to Spatie Media
 */
class LeasePdfGenerator
{
    /**
     * Render the lease as a PDF and return the raw bytes.
     */
    public function render(Lease $lease): string
    {
        $lease->loadMissing(['renter', 'unit.property']);

        $client = Client::find($lease->tenant_id);

        $html = View::make('leases.contract', [
            'lease' => $lease,
            'client' => $client,
        ])->render();

        return Browsershot::html($html)
            ->format('A4')
            // Running as root inside the Sail container — Chromium refuses
            // without --no-sandbox. In production this still applies because
            // the PHP-FPM container also runs as a constrained user inside
            // a hardened sandbox of its own.
            ->noSandbox()
            ->showBackground()
            ->pdf();
    }

    /**
     * Render + persist the PDF onto the lease via Spatie Media Library
     * (replacing any previous PDF because the contract collection is
     * declared singleFile). Returns the media filename for convenience.
     */
    public function store(Lease $lease): string
    {
        $bytes = $this->render($lease);

        $filename = 'lease-'.substr($lease->id, 0, 8).'.pdf';
        $tmp = tempnam(sys_get_temp_dir(), 'lease-').'.pdf';
        file_put_contents($tmp, $bytes);

        $media = $lease
            ->addMedia($tmp)
            ->usingFileName($filename)
            ->toMediaCollection('contract');

        return $media->file_name;
    }
}
