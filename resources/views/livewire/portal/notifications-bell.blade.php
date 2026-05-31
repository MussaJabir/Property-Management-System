<div class="relative" wire:poll.30s>
    <button type="button" wire:click="toggle"
            class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
            aria-label="{{ __('Notifications') }}">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 z-40 mt-2 w-80 origin-top-right rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-2 dark:border-zinc-800">
                <p class="text-sm font-semibold">{{ __('Notifications') }}</p>
                @if ($unreadCount > 0)
                    <button wire:click="markAllRead" class="text-xs text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-200">
                        {{ __('Mark all read') }}
                    </button>
                @endif
            </div>
            <ul class="max-h-96 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
                @forelse ($items as $n)
                    @php $d = $n->data; @endphp
                    <li class="{{ $n->read_at ? 'opacity-60' : '' }}">
                        <a href="{{ $d['url'] ?? '#' }}"
                           wire:click="markRead('{{ $n->id }}')"
                           class="block px-4 py-3 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <p class="font-medium">
                                @switch($d['type'] ?? '')
                                    @case('invoice_issued')   {{ __('New invoice :n', ['n' => $d['invoice_number'] ?? '']) }} @break
                                    @case('invoice_overdue')  {{ __('Invoice :n overdue', ['n' => $d['invoice_number'] ?? '']) }} @break
                                    @case('payment_received') {{ __('Payment received: :a', ['a' => $d['amount'] ?? '']) }} @break
                                    @default {{ __('Notification') }}
                                @endswitch
                            </p>
                            @if (! empty($d['amount']) && ($d['type'] ?? '') !== 'payment_received')
                                <p class="mt-0.5 text-xs text-zinc-500">{{ $d['amount'] }}</p>
                            @endif
                            <p class="mt-1 text-[11px] text-zinc-400">{{ $n->created_at?->diffForHumans() }}</p>
                        </a>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-xs text-zinc-500">{{ __('No notifications yet.') }}</li>
                @endforelse
            </ul>
        </div>
    @endif
</div>
