<?php

declare(strict_types=1);

namespace App\Filament\Operator\Resources\ContactSubmissions\Pages;

use App\Filament\Operator\Resources\ContactSubmissions\ContactSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListContactSubmissions extends ListRecords
{
    protected static string $resource = ContactSubmissionResource::class;
}
