<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScopedModel;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * One audit-log entry: who did what to which record, with old and new
 * values. Scoped to a workspace like every other record, so a client only
 * ever sees its own history. Entries are never edited by the app.
 */
class Activity extends BaseActivity
{
    use TenantScopedModel;
}
