<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Client;
use App\Models\FeedbackReport;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Shows a feedback screenshot from the private disk to a signed-in super
 * admin (registered on the admin panel's authenticated routes).
 *
 * The file was saved inside the client's workspace, and tenancy gives the
 * local disk a per-client root, so it is read back inside that client's
 * tenancy too.
 */
class FeedbackScreenshotController
{
    public function __invoke(int $report): Response
    {
        /** @var FeedbackReport|null $feedback */
        $feedback = FeedbackReport::query()->with('client')->find($report);
        $path = $feedback?->screenshot_path;
        $client = $feedback?->client;

        abort_unless(is_string($path) && $client instanceof Client, 404);

        /** @var array{0: string, 1: string}|null $file */
        $file = $client->run(function () use ($path): ?array {
            $disk = Storage::disk(config('filesystems.default'));

            return $disk->exists($path) ? [(string) $disk->get($path), (string) $disk->mimeType($path)] : null;
        });

        abort_if($file === null, 404);

        return response($file[0], 200, [
            'Content-Type' => $file[1],
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
