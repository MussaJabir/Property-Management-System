<?php

declare(strict_types=1);

namespace App\Policies;

class PaymentPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'billing';
}
