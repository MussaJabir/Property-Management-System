<?php

declare(strict_types=1);

namespace App\Policies;

class ExpensePolicy extends OperatorResourcePolicy
{
    protected string $domain = 'expenses';
}
