<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

class LocaleSwitcher extends Component
{
    public function switch(string $locale): void
    {
        $supported = config('app.supported_locales', ['en', 'sw']);

        if (! in_array($locale, $supported, true)) {
            return;
        }

        session()->put('locale', $locale);

        $this->redirect(request()->header('Referer') ?: '/', navigate: false);
    }

    #[Computed]
    public function current(): string
    {
        return app()->getLocale();
    }

    #[Computed]
    public function supported(): array
    {
        return config('app.supported_locales', ['en', 'sw']);
    }

    public function render()
    {
        return view('livewire.locale-switcher');
    }
}
