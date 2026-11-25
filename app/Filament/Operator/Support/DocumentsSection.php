<?php

declare(strict_types=1);

namespace App\Filament\Operator\Support;

use App\Support\Files;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Section;

/**
 * The "Documents" form section shared by properties, units, renters and
 * rental applications: private files (PDF, images, Word) staff can open and
 * download through short-lived links.
 */
final class DocumentsSection
{
    public static function make(string $description): Section
    {
        return Section::make(__('Documents'))
            ->description($description)
            ->collapsed(fn (string $operation): bool => $operation === 'create')
            ->components([
                SpatieMediaLibraryFileUpload::make('documents')
                    ->hiddenLabel()
                    ->collection('documents')
                    ->disk(Files::privateDisk())
                    ->visibility('private')
                    ->multiple()
                    ->openable()
                    ->downloadable()
                    ->acceptedFileTypes([
                        'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
                        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->maxSize(10240)
                    ->helperText(__('PDF, photo or Word file. Max 10 MB each.'))
                    ->columnSpanFull(),
            ]);
    }
}
