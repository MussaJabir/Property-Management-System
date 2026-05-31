<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationsBell extends Component
{
    public bool $open = false;

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function markAllRead(): void
    {
        Auth::guard('renter')->user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    public function markRead(string $id): void
    {
        Auth::guard('renter')->user()?->notifications()->whereKey($id)->update(['read_at' => now()]);
    }

    #[On('refresh-notifications')]
    public function refresh(): void
    {
        // Just re-renders.
    }

    public function render(): View
    {
        $user = Auth::guard('renter')->user();

        return view('livewire.portal.notifications-bell', [
            'unreadCount' => $user?->unreadNotifications()->count() ?? 0,
            'items' => $user?->notifications()->latest()->limit(10)->get() ?? collect(),
        ]);
    }
}
