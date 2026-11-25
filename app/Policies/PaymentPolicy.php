<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Money already received is never changed or deleted quietly: a completed
 * (or reversed) payment is reversed with a reason instead, and a corrected
 * one recorded. Pending and failed payments can still be edited or removed.
 */
class PaymentPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'billing';

    public function update(User $user, Model $record): bool
    {
        return parent::update($user, $record) && ! $this->isFinal($record);
    }

    public function delete(User $user, Model $record): bool
    {
        return parent::delete($user, $record) && ! $this->isFinal($record);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return parent::forceDelete($user, $record) && ! $this->isFinal($record);
    }

    /** Reverse a completed payment (bounced cheque, recorded by mistake). */
    public function reverse(User $user, Payment $payment): bool
    {
        return parent::update($user, $payment) && $payment->isCompleted();
    }

    private function isFinal(Model $record): bool
    {
        return $record instanceof Payment && in_array($record->status, [Payment::STATUS_COMPLETED, Payment::STATUS_REVERSED], true);
    }
}
