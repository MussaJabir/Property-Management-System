<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Inspection;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InspectionPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'tenancy';

    /** A completed inspection is a record: it can be read, not changed. */
    public function update(User $user, Model $record): bool
    {
        return parent::update($user, $record) && ! ($record instanceof Inspection && $record->isCompleted());
    }

    public function delete(User $user, Model $record): bool
    {
        return parent::delete($user, $record) && ! ($record instanceof Inspection && $record->isCompleted());
    }
}
