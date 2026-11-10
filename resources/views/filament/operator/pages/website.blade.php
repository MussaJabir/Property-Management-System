<x-filament-panels::page>
    @php
        $statuses = [
            'shown' => [__('Showing'), 'success'],
            'hidden' => [__('Hidden'), 'gray'],
            'waiting' => [__('Hidden until you post'), 'gray'],
            'auto' => [__('Automatic'), 'info'],
        ];
    @endphp

    <div class="flex flex-col gap-6">
        {{-- The website's address --}}
        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    @if ($logo)
                        <img src="{{ $logo }}" alt="{{ __('Logo') }}" class="h-11 w-auto max-w-40 shrink-0 object-contain">
                    @endif
                    <div class="min-w-0">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Your website address') }}</p>
                        <p class="break-all text-base font-semibold text-gray-950 dark:text-white">{{ $siteLabel }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2" x-data="{ copied: false, link: @js($site) }">
                    <x-filament::button color="gray" icon="heroicon-o-clipboard-document"
                        x-on:click="navigator.clipboard.writeText(link); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-show="! copied">{{ __('Copy link') }}</span>
                        <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                    </x-filament::button>
                    <x-filament::button color="gray" tag="a" :href="$whatsappShare" target="_blank" icon="heroicon-o-chat-bubble-left-right">
                        {{ __('Share on WhatsApp') }}
                    </x-filament::button>
                    <x-filament::button tag="a" :href="$site" target="_blank" icon="heroicon-o-arrow-top-right-on-square">
                        {{ __('View my website') }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>

        <div class="flex flex-wrap items-start gap-6">
            {{-- Pages --}}
            <x-filament::section :heading="__('Pages')" :description="__('Open a page to change what it says.')" class="min-w-0 flex-[2_1_34rem]">
                <ul class="-my-3 divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($rows as $row)
                        @php([$statusLabel, $statusColor] = $statuses[$row['status']])
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3.5">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">
                                <x-filament::icon :icon="$row['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-[1_1_12rem]">
                                <p class="font-semibold text-gray-950 dark:text-white">{{ $row['name'] }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $row['about'] }}</p>
                            </div>
                            <x-filament::badge :color="$statusColor">{{ $statusLabel }}</x-filament::badge>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($row['links'] as [$label, $url])
                                    @if ($url)
                                        <x-filament::button color="gray" size="sm" tag="a" :href="$url">{{ $label }}</x-filament::button>
                                    @endif
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>

            <div class="flex min-w-0 flex-[1_1_18rem] flex-col gap-6">
                {{-- Messages --}}
                <x-filament::section :heading="__('Messages from your website')" :description="__('When someone fills the contact form.')">
                    <p class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-gray-950 dark:text-white">{{ $newMessages }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('new') }}</span>
                    </p>
                    @if ($messagesUrl)
                        <a href="{{ $messagesUrl }}" class="mt-3 inline-block text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ __('See all messages') }}</a>
                    @endif
                </x-filament::section>

                {{-- Look --}}
                <x-filament::section :heading="__('Look')" :description="__('Your logo and brand color.')">
                    <dl class="flex flex-col gap-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Logo') }}</dt>
                            <dd>
                                @if ($logo)
                                    <img src="{{ $logo }}" alt="" class="h-7 w-auto max-w-32 object-contain">
                                @else
                                    <span class="font-semibold text-warning-700 dark:text-warning-400">{{ __('Not set') }}</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __('Brand color') }}</dt>
                            <dd class="flex items-center gap-2 font-semibold text-gray-950 dark:text-white">
                                @if ($color)
                                    <span class="h-4 w-4 rounded" style="background: {{ $color }}"></span>{{ $color }}
                                @else
                                    {{ __('Default') }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                    @if ($settingsUrl)
                        <a href="{{ $settingsUrl }}" class="mt-3 inline-block text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ __('Change look') }}</a>
                    @endif
                </x-filament::section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
