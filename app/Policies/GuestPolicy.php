<?php

declare(strict_types=1);

namespace App\Policies;

class GuestPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'frontdesk';
}
