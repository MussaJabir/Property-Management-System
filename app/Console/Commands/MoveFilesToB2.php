<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\CmsPage;
use App\Models\FeedbackReport;
use App\Models\Receipt;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One-time move of every uploaded file from the server's disk to B2.
 *
 * Website files (logos, banners, room and property photos) go to the public
 * bucket; everything else (receipts, maintenance photos, expense receipts,
 * lease PDFs, feedback screenshots) to the private one. Originals are never
 * deleted, and running it again only copies what is still missing, so it is
 * safe to run before and after switching FILESYSTEM_DISK to b2.
 */
class MoveFilesToB2 extends Command
{
    protected $signature = 'pms:move-files-to-b2 {--dry-run : Only report what would be copied}';

    protected $description = 'Copy uploaded files from the server disk to the B2 buckets (never deletes)';

    /** @var array<string, array{copied: int, already: int, missing: int}> */
    private array $tally = [];

    /** @var array<string, Filesystem|null> */
    private array $tenantDisks = [];

    public function handle(): int
    {
        $this->tally = [];
        $this->tenantDisks = [];

        foreach (['b2', 'b2_public'] as $disk) {
            foreach (['key', 'secret', 'bucket', 'endpoint'] as $setting) {
                if (blank(config("filesystems.disks.{$disk}.{$setting}"))) {
                    $this->error("The {$disk} disk has no {$setting}. Add the B2 settings to .env first.");

                    return self::FAILURE;
                }
            }
        }

        if ($this->isDryRun()) {
            $this->warn('Dry run: nothing will be copied or changed.');
        }

        $this->moveMedia();
        $this->moveLogos();
        $this->moveBanners();
        $this->movePrivateFiles('Receipt PDFs', Receipt::class, 'pdf_path');
        $this->movePrivateFiles('Feedback screenshots', FeedbackReport::class, 'screenshot_path');

        $this->table(['Files', 'Copied', 'Already on B2', 'Not found'], collect($this->tally)
            ->map(fn (array $row, string $label): array => [$label, $row['copied'], $row['already'], $row['missing']])
            ->values()
            ->all());

        return self::SUCCESS;
    }

    /** Photos and PDFs kept by Media Library; their records are pointed at B2 once every file is there. */
    private function moveMedia(): void
    {
        Media::query()->whereIn('disk', ['public', 'local'])->lazyById()->each(function (Media $media): void {
            $label = $media->disk === 'public' ? 'Website photos' : 'Private photos and PDFs';
            $target = $media->disk === 'public' ? 'b2_public' : 'b2';
            $folder = $media->getKey().'/';

            $sources = $this->sourcesFor($media->disk, $this->tenantOfMedia($media));
            $source = collect($sources)->first(fn (Filesystem $disk): bool => $disk->exists($folder.$media->file_name));

            if ($source === null) {
                $this->count($label, 'missing');

                return;
            }

            $copied = false;
            foreach ($source->allFiles($folder) as $path) {
                $copied = $this->copy($path, [$source], Storage::disk($target)) === 'copied' || $copied;
            }
            $this->count($label, $copied ? 'copied' : 'already');

            if (! $this->isDryRun()) {
                $media->forceFill(['disk' => $target, 'conversions_disk' => $target])->save();
            }
        });
    }

    private function moveLogos(): void
    {
        Client::query()->whereNotNull('logo_path')->each(function (Client $client): void {
            $this->count('Logos', $this->copy(
                (string) $client->logo_path,
                [...$this->sourcesFor('public', null), ...$this->sourcesFor('local', $client->getKey())],
                Storage::disk('b2_public'),
            ));
        });
    }

    private function moveBanners(): void
    {
        CmsPage::withoutGlobalScopes()->each(function (CmsPage $page): void {
            foreach ($page->blocks ?? [] as $block) {
                $path = $block['data']['background_image'] ?? null;

                if (is_string($path) && $path !== '') {
                    $this->count('Banner photos', $this->copy($path, $this->sourcesFor('public', null), Storage::disk('b2_public')));
                }
            }
        });
    }

    /** @param class-string<Model> $model */
    private function movePrivateFiles(string $label, string $model, string $column): void
    {
        $model::withoutGlobalScopes()->whereNotNull($column)->each(function (Model $row) use ($label, $column): void {
            $this->count($label, $this->copy(
                (string) $row->getAttribute($column),
                $this->sourcesFor('local', (string) $row->getAttribute('tenant_id')),
                Storage::disk('b2'),
            ));
        });
    }

    /**
     * Where a file on $disk may sit on the server. Private files written
     * inside a workspace live in that workspace's own folder.
     *
     * @return list<Filesystem>
     */
    private function sourcesFor(string $disk, ?string $tenantId): array
    {
        $sources = [];

        if ($disk === 'local' && $tenantId !== null && $tenantId !== '') {
            $tenantDisk = $this->tenantDisk($tenantId);
            if ($tenantDisk !== null) {
                $sources[] = $tenantDisk;
            }
        }

        $sources[] = Storage::disk($disk);

        return $sources;
    }

    private function tenantDisk(string $tenantId): ?Filesystem
    {
        if (! array_key_exists($tenantId, $this->tenantDisks)) {
            $client = Client::find($tenantId);
            $this->tenantDisks[$tenantId] = $client?->run(fn (): Filesystem => Storage::disk('local'));
        }

        return $this->tenantDisks[$tenantId];
    }

    private function tenantOfMedia(Media $media): ?string
    {
        $class = Relation::getMorphedModel($media->model_type) ?? $media->model_type;

        if (! is_a($class, Model::class, true)) {
            return null;
        }

        $query = $class::withoutGlobalScopes();
        $owner = $query->find($media->model_id);
        $tenantId = $owner?->getAttribute('tenant_id');

        return is_string($tenantId) ? $tenantId : null;
    }

    /**
     * @param  list<Filesystem>  $sources
     * @return 'copied'|'already'|'missing'
     */
    private function copy(string $path, array $sources, Filesystem $target): string
    {
        if ($target->exists($path)) {
            return 'already';
        }

        foreach ($sources as $source) {
            if (! $source->exists($path)) {
                continue;
            }

            if (! $this->isDryRun()) {
                $stream = $source->readStream($path);
                $target->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            return 'copied';
        }

        return 'missing';
    }

    /** @param 'copied'|'already'|'missing' $result */
    private function count(string $label, string $result): void
    {
        $this->tally[$label] ??= ['copied' => 0, 'already' => 0, 'missing' => 0];
        $this->tally[$label][$result]++;
    }

    private function isDryRun(): bool
    {
        return (bool) $this->option('dry-run');
    }
}
