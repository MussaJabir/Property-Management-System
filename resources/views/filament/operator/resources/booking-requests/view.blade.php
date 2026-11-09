<x-filament-panels::page>
    @php
        $statusColor = match ($request->status) {
            \App\Models\BookingRequest::STATUS_NEW => 'warning',
            \App\Models\BookingRequest::STATUS_CONFIRMED, \App\Models\BookingRequest::STATUS_HANDLED => 'success',
            default => 'gray',
        };
        $historyIcons = [
            'note' => ['heroicon-o-pencil-square', 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400'],
            'call' => ['heroicon-o-phone', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'],
            'whatsapp' => ['heroicon-o-chat-bubble-left-right', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'],
            'alert' => ['heroicon-o-bell', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400'],
            'status' => ['heroicon-o-check-circle', 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300'],
        ];
        $historyTitles = [
            'note' => __('added a note'),
            'call' => __('tapped Call'),
            'whatsapp' => __('opened WhatsApp'),
            'alert' => __('Alert sent'),
            'status' => __('changed the status'),
        ];
    @endphp

    <div class="flex flex-col gap-6 max-sm:pb-24">
        {{-- Status and the ways to answer. On phones the status buttons sit fixed at the bottom. --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-filament::badge :color="$statusColor" size="lg">{{ $statusLabel }}</x-filament::badge>

            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto">
                    {{ $this->phoneCallAction }}
                    {{ $this->whatsappAction }}
                </div>

                @if ($request->isOpen())
                    <div class="flex gap-2 max-sm:fixed max-sm:inset-x-0 max-sm:bottom-0 max-sm:z-20 max-sm:border-t max-sm:border-gray-200 max-sm:bg-white max-sm:px-4 max-sm:py-3 max-sm:dark:border-white/10 max-sm:dark:bg-gray-900 max-sm:[&>*]:flex-1">
                        @if ($this->confirmAction->isVisible())
                            {{ $this->confirmAction }}
                        @endif
                        @if ($this->handledAction->isVisible())
                            {{ $this->handledAction }}
                        @endif
                        @if ($this->declineAction->isVisible())
                            {{ $this->declineAction }}
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left: the request, the message, notes and history --}}
            <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
                <x-filament::section :heading="__('Contact and request')">
                    <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Phone') }}</dt>
                            <dd class="mt-1 font-semibold"><a href="tel:{{ $request->phone }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $phone }}</a></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Wants') }}</dt>
                            <dd class="mt-1 font-semibold">{{ $wants }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Came from') }}</dt>
                            <dd class="mt-1 font-semibold">{{ __('Room page on the website') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Asked before?') }}</dt>
                            <dd class="mt-1 font-semibold">
                                {{ $askedBefore > 0 ? trans_choice(':count earlier request from this number|:count earlier requests from this number', $askedBefore) : __('First request from this number') }}
                            </dd>
                        </div>
                        @if ($request->decline_reason)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Reason declined') }}</dt>
                                <dd class="mt-1 font-semibold">{{ $request->decline_reason }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-filament::section>

                <x-filament::section :heading="__('Their message')">
                    @if ($request->message)
                        <p class="whitespace-pre-line rounded-lg bg-gray-50 px-4 py-3 text-[15px] leading-relaxed text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ $request->message }}</p>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No message, only their contact details.') }}</p>
                    @endif
                </x-filament::section>

                <x-filament::section :heading="__('Notes and history')">
                    <form wire:submit="saveNote" class="flex flex-col gap-2">
                        <label for="request-note" class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Add a note for your team') }}</label>
                        <textarea id="request-note" wire:model="note" rows="3" maxlength="1000"
                                  placeholder="{{ __('e.g. Called her, coming Saturday 10am to view') }}"
                                  class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"></textarea>
                        @error('note') <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p> @enderror
                        <div>
                            <x-filament::button type="submit" color="gray" wire:loading.attr="disabled" wire:target="saveNote">{{ __('Save note') }}</x-filament::button>
                        </div>
                    </form>

                    <ol class="mt-5 divide-y divide-gray-100 border-t border-gray-200 dark:divide-white/5 dark:border-white/10">
                        @foreach ($history as $entry)
                            @php([$icon, $tone] = $historyIcons[$entry->type] ?? $historyIcons['status'])
                            <li class="flex gap-3 py-3.5">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $tone }}">
                                    <x-filament::icon :icon="$icon" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 text-sm leading-relaxed">
                                    <span class="font-semibold">{{ $entry->type === 'alert' ? $historyTitles['alert'] : ($entry->user?->name ?? __('Someone')) }}</span>
                                    @if ($entry->type !== 'alert' && $entry->type !== 'status')
                                        {{ $historyTitles[$entry->type] ?? '' }}
                                    @endif
                                    <span class="text-gray-500 dark:text-gray-400">· {{ $entry->created_at?->format('d/m H:i') }}</span>
                                    @if ($entry->body)
                                        <div class="whitespace-pre-line break-words text-gray-700 dark:text-gray-300">{{ $entry->body }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                        <li class="flex gap-3 py-3.5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                <x-filament::icon icon="heroicon-o-inbox-arrow-down" class="h-4 w-4" />
                            </span>
                            <div class="text-sm leading-relaxed">
                                <span class="font-semibold">{{ __('Request received') }}</span>
                                <span class="text-gray-500 dark:text-gray-400">· {{ $request->created_at?->format('d/m H:i') }}</span>
                                <div class="text-gray-700 dark:text-gray-300">{{ __('From the room page on the website') }}</div>
                            </div>
                        </li>
                    </ol>
                </x-filament::section>
            </div>

            {{-- Right: the room --}}
            <div class="flex min-w-0 flex-col gap-6">
                @if ($unit)
                    <x-filament::section>
                        @if ($photo)
                            <div class="-mx-6 -mt-6 mb-4 h-36 overflow-hidden rounded-t-xl bg-gray-100 dark:bg-white/5">
                                <img src="{{ $photo }}" alt="{{ $unit->publicTitle() }}" class="h-full w-full object-cover">
                            </div>
                        @endif
                        <div class="flex flex-col gap-2.5 text-sm">
                            <div class="text-base font-bold">{{ $unit->publicTitle() }}</div>
                            @if ($unit->property?->location)
                                <div class="text-gray-600 dark:text-gray-400">{{ collect([$unit->property->location->name, $unit->property->location->district])->filter()->unique()->implode(', ') }}</div>
                            @endif
                            @foreach ($prices as $price)
                                <div class="flex justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">{{ __('Price') }}</span><b>{{ $price['currency'] }} {{ $price['amount'] }} / {{ $price['per'] }}</b></div>
                            @endforeach
                            <div class="flex justify-between gap-3">
                                <span class="text-gray-500 dark:text-gray-400">{{ __('Now') }}</span>
                                <b class="{{ $unit->isVacant() ? 'text-emerald-700 dark:text-emerald-400' : '' }}">{{ $unit->isVacant() ? __('Vacant') : __('Occupied') }}</b>
                            </div>
                            <div class="flex justify-between gap-3"><span class="text-gray-500 dark:text-gray-400">{{ __('Other open requests for it') }}</span><b>{{ $otherOpen }}</b></div>
                            <div class="flex flex-wrap gap-4 pt-1">
                                @if ($unitUrl)
                                    <a href="{{ $unitUrl }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ __('Open room') }}</a>
                                @endif
                                @if ($publicUrl)
                                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ __('See public page') }}</a>
                                @endif
                            </div>
                        </div>
                    </x-filament::section>
                @endif

                @if ($request->isOpen())
                    <div class="rounded-xl border border-primary-200 bg-primary-50 p-4 text-sm leading-relaxed text-primary-900 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-200">
                        <div class="mb-1 font-bold">{{ __('Next step') }}</div>
                        {{ $request->isStay()
                            ? __('Call :name to check the dates, then press Confirm & book, or Decline.', ['name' => $request->full_name])
                            : __('Call :name, agree on a viewing, then press Called back.', ['name' => $request->full_name]) }}
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-filament-panels::page>
