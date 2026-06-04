<?php

declare(strict_types=1);

namespace App\Policies;

class ExpenseCategoryPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'expenses';
}
