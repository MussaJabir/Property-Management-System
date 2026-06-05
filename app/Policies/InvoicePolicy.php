<?php

declare(strict_types=1);

namespace App\Policies;

class InvoicePolicy extends OperatorResourcePolicy
{
    protected string $domain = 'billing';
}
