<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\CmsAnnouncements\Pages;

use App\Filament\Operator\Resources\CmsAnnouncements\CmsAnnouncementResource;
use App\Models\CmsAnnouncement;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCmsAnnouncement extends EditRecord
{
    protected static string $resource = CmsAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return CmsAnnouncementResource::fillPublishing($data);
    }

    /** @param array<string, mixed> $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var CmsAnnouncement $post */
        $post = $this->getRecord();

        return CmsAnnouncementResource::savePublishing($data, $post);
    }
}
