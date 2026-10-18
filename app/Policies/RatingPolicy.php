<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Ratings are read-only for staff; renters and guests write them. */
class RatingPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'tenancy';

    public function viewAny(User $user): bool
    {
        // Front desk staff see guest ratings too.
        return $this->canView($user) || $user->can('frontdesk.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
