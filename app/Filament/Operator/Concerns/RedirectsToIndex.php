<?php

namespace App\Filament\Operator\Concerns;

/**
 * Send the operator back to the resource's list page after a successful
 * create (or edit) instead of the default Edit/View page. Operators told us
 * they expect to "see it in the list" right after saving — this matches that
 * mental model and reinforces the success feedback.
 *
 * Use on a Filament CreateRecord / EditRecord page.
 */
trait RedirectsToIndex
{
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
