<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\CmsPage;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Generic renderer for any of the fixed CMS pages (home / about / news /
 * contact). The units page is handled by a separate Livewire component
 * because it carries filterable, paginated state.
 */
class Page extends Component
{
    public string $slug;

    public function mount(string $slug = 'home'): void
    {
        if (! in_array($slug, CmsPage::ALL_SLUGS, true)) {
            throw new NotFoundHttpException;
        }
        $this->slug = $slug;
    }

    #[Layout('components.layouts.public')]
    public function render(): View
    {
        $page = CmsPage::query()->where('slug', $this->slug)->first();

        if (! $page) {
            throw new NotFoundHttpException;
        }

        return view('livewire.public.page', ['page' => $page]);
    }
}
