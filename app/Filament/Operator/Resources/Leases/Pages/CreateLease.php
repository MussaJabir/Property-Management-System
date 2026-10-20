<?php

namespace App\Filament\Operator\Resources\Leases\Pages;

use App\Filament\Operator\Concerns\RedirectsToIndex;
use App\Filament\Operator\Resources\Leases\LeaseResource;
use App\Filament\Operator\Resources\Leases\Schemas\LeaseForm;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Renter;
use App\Models\Unit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Throwable;

/**
 * One-screen lease: unit, renter (added inline if new), rent, save. With
 * "Start the lease now" on (the default) the lease is activated straight
 * away, which marks the unit occupied and issues the first invoice.
 *
 * `?unit=<id>` preselects a vacant unit (Today → Vacant units) and
 * `?renter=<id>` the renter (after adding a renter).
 */
class CreateLease extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = LeaseResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('Add Lease');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label(__('Save lease'));
    }

    public function form(Schema $schema): Schema
    {
        return LeaseForm::createForm($schema);
    }

    protected function afterFill(): void
    {
        $unitId = request()->query('unit');
        $unit = is_string($unitId) ? Unit::query()->vacant()->longTerm()->find($unitId) : null;

        if ($unit !== null) {
            $this->data = array_merge($this->data ?? [], ['unit_id' => $unit->id], LeaseForm::unitDefaults($unit));
        }

        $renterId = request()->query('renter');
        if (is_string($renterId) && Renter::query()->whereKey($renterId)->exists()) {
            $this->data = array_merge($this->data ?? [], ['renter_id' => $renterId]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Always created pending; activate() below (or later, from the
        // list) moves it on. Status is never taken from the form.
        $data['status'] = Lease::STATUS_PENDING;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Lease $lease */
        $lease = $this->getRecord();

        if (! ($this->data['activate_now'] ?? false)) {
            return;
        }

        try {
            $lease->activate(auth()->id());
        } catch (Throwable $e) {
            Notification::make()->danger()->title(__('Saved, but could not start the lease'))->body($e->getMessage())->send();
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var Lease $lease */
        $lease = $this->getRecord();

        if (! $lease->isActive()) {
            return Notification::make()->success()->title(__('Lease saved as pending'))
                ->body(__('Start it from the Leases list once it is signed.'));
        }

        /** @var Invoice|null $invoice */
        $invoice = $lease->invoices()->latest()->first();

        return Notification::make()->success()->title(__('Lease started'))
            ->body($invoice !== null
                ? __('Invoice :number issued for :amount.', ['number' => $invoice->invoice_number, 'amount' => $invoice->formatted_total])
                : __('The unit is now occupied.'));
    }
}
