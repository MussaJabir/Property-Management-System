<?php

declare(strict_types=1);

namespace App\Filament\Operator\Pages;

use App\Filament\Operator\Actions\RecordPaymentAction;
use App\Filament\Operator\Resources\Invoices\InvoiceResource;
use App\Filament\Operator\Resources\Renters\RenterResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

/**
 * "Today": what needs the operator now, with the everyday actions one click
 * away. Widgets sit on a 5-column grid on wide screens so the work lists get
 * three columns and the side cards two.
 */
class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?int $navigationSort = -10;

    public static function getNavigationLabel(): string
    {
        return __('Today');
    }

    public function getTitle(): string
    {
        return __('Today');
    }

    public function getHeading(): string
    {
        $hour = (int) now()->format('G');
        $greeting = match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 17 => __('Good afternoon'),
            default => __('Good evening'),
        };
        $firstName = strtok((string) auth()->user()?->name, ' ') ?: '';

        return trim($greeting.', '.$firstName, ', ');
    }

    public function getSubheading(): string
    {
        return now()->translatedFormat('l, j F Y').' · '.__("here's what needs you today");
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 5];
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            RecordPaymentAction::make(),

            Action::make('addRenter')
                ->label(__('Add renter'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->url(fn (): string => RenterResource::getUrl('create'))
                ->visible(fn (): bool => RenterResource::canCreate()),

            Action::make('newInvoice')
                ->label(__('New invoice'))
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->color('gray')
                ->url(fn (): string => InvoiceResource::getUrl('create'))
                ->visible(fn (): bool => InvoiceResource::canCreate()),
        ];
    }
}
