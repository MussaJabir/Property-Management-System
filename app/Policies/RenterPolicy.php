<?php

declare(strict_types=1);

namespace App\Policies;

class RenterPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'tenancy';
}
