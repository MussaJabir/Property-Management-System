<?php

namespace App\Filament\Operator\Resources\Leases\Pages;

use App\Filament\Operator\Resources\Leases\LeaseResource;
use App\Filament\Operator\Resources\Leases\Schemas\LeaseForm;
use App\Models\Lease;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Schema;

/**
 * Create flow uses Filament's HasWizard trait — four steps: Renter → Unit →
 * Terms → Confirm. Each step ships its own validation gate before allowing
 * progression. The submit action lives on the final step.
 *
 * After create, the lease starts in `pending` status. The operator must
 * explicitly activate it from the list page (which then marks the unit
 * occupied and writes a lease_history row).
 */
class CreateLease extends CreateRecord
{
    use HasWizard;

    protected static string $resource = LeaseResource::class;

    protected function getSteps(): array
    {
        return LeaseForm::wizardSteps();
    }

    /**
     * Override the resource form (which is the edit-flavored form with
     * disabled renter/unit fields) so the wizard's fields are the source of
     * truth for state.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(null)
            ->components([$this->getWizardComponent()]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Always start in pending. Status is governed by activate() /
        // terminate() / end() afterwards — never via the form.
        $data['status'] = Lease::STATUS_PENDING;

        return $data;
    }
}
