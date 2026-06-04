<?php

declare(strict_types=1);

namespace App\Policies;

class CmsPagePolicy extends OperatorResourcePolicy
{
    protected string $domain = 'cms';
}
