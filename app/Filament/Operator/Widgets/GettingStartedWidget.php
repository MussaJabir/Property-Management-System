<?php

namespace App\Filament\Operator\Widgets;

use App\Models\Property;
use App\Models\Unit;
use Filament\Widgets\Widget;

class GettingStartedWidget extends Widget
{
    protected string $view = 'filament.operator.widgets.getting-started';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    /**
     * Only show the onboarding checklist while the workspace is still being
     * set up. Once there is at least one property and one unit, an
     * established operator no longer needs it cluttering the dashboard.
     */
    public static function canView(): bool
    {
        return Property::query()->doesntExist() || Unit::query()->doesntExist();
    }
}
