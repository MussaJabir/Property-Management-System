<?php

declare(strict_types=1);

namespace App\Policies;

class ReceiptPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'billing';
}
