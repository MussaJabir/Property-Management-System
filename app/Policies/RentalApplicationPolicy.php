<?php

declare(strict_types=1);

namespace App\Policies;

class RentalApplicationPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'tenancy';
}
