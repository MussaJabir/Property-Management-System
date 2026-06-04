<?php

declare(strict_types=1);

namespace App\Policies;

class CmsAnnouncementPolicy extends OperatorResourcePolicy
{
    protected string $domain = 'cms';
}
