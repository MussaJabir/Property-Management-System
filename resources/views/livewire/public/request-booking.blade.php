@php
    $client = tenant();
    $field = 'min-h-[48px] w-full rounded-xl border border-[#c9c6be] bg-white px-3 text-base text-[var(--ink)] focus:border-[var(--ink)] focus:ring-0';
    $label = 'flex flex-col gap-1.5 text-sm font-semibold';
    $error = 'text-sm font-normal text-[#b42318]';
    $whatsapp = $client->whatsappUrl($kind === 'stay'
        ? __('Hello, I would like to book :room from :from to :to.', ['room' => $unit->publicTitle(), 'from' => $check_in, 'to' => $check_out])
        : __('Hello, I am interested in :room. Is it free?', ['room' => $unit->publicTitle()]));
@endphp

<div class="flex flex-col gap-4">
    @if ($sent)
        <div class="flex flex-col items-center gap-3 py-2 text-center" role="status">
            <span class="grid h-14 w-14 place-items-center rounded-full bg-[#e4f3ee]">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="#0f766e" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l4 4L19 6"/></svg>
            </span>
            <h3 class="text-xl font-extrabold">{{ __('Request sent') }}</h3>
            <p class="text-[15px] text-[#3d3b37]">{{ __(':name will call you on :phone to confirm.', ['name' => $client->name, 'phone' => $phone]) }}</p>
            @if ($whatsapp)
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener"
                   class="inline-flex min-h-[48px] items-center gap-2 rounded-xl border border-[var(--ink)] px-4 font-bold">
                    <x-cms.whatsapp-icon class="h-5 w-5" /> {{ __('Message them on WhatsApp') }}
                </a>
            @endif
        </div>
    @else
        <form wire:submit="submit" class="flex flex-col gap-4">
            @if ($offersBoth)
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-[#f4f2ee] p-1">
                    <button type="button" wire:click="$set('kind', 'stay')"
                            class="min-h-[40px] rounded-lg text-sm font-semibold {{ $kind === 'stay' ? 'bg-white shadow-sm' : 'text-[var(--muted)]' }}">{{ __('By the night') }}</button>
                    <button type="button" wire:click="$set('kind', 'enquiry')"
                            class="min-h-[40px] rounded-lg text-sm font-semibold {{ $kind === 'enquiry' ? 'bg-white shadow-sm' : 'text-[var(--muted)]' }}">{{ __('Monthly') }}</button>
                </div>
            @endif

            @if ($kind === 'stay')
                <div class="grid grid-cols-2 gap-3">
                    <label for="rb-in" class="{{ $label }}">{{ __('Check in') }}
                        <input id="rb-in" type="date" wire:model.live="check_in" min="{{ today()->toDateString() }}" class="{{ $field }}">
                    </label>
                    <label for="rb-out" class="{{ $label }}">{{ __('Check out') }}
                        <input id="rb-out" type="date" wire:model.live="check_out" min="{{ today()->addDay()->toDateString() }}" class="{{ $field }}">
                    </label>
                </div>
                @error('check_in') <p class="{{ $error }}">{{ $message }}</p> @enderror
                @error('check_out') <p class="{{ $error }}">{{ $message }}</p> @enderror
                <label for="rb-guests" class="{{ $label }}">{{ __('Guests') }}
                    <input id="rb-guests" type="number" min="1" max="20" inputmode="numeric" wire:model="guests" class="{{ $field }}">
                    @error('guests') <span class="{{ $error }}">{{ $message }}</span> @enderror
                </label>
            @else
                <label for="rb-movein" class="{{ $label }}">{{ __('When would you move in?') }}
                    <input id="rb-movein" type="date" wire:model="move_in" min="{{ today()->toDateString() }}" class="{{ $field }}">
                    @error('move_in') <span class="{{ $error }}">{{ $message }}</span> @enderror
                </label>
            @endif

            <label for="rb-name" class="{{ $label }}">{{ __('Your name') }}
                <input id="rb-name" type="text" wire:model="full_name" autocomplete="name" placeholder="{{ __('e.g. Neema Juma') }}" class="{{ $field }}">
                @error('full_name') <span class="{{ $error }}">{{ $message }}</span> @enderror
            </label>
            <label for="rb-phone" class="{{ $label }}">{{ __('Phone number') }}
                <input id="rb-phone" type="tel" wire:model="phone" autocomplete="tel" inputmode="tel" placeholder="0712 345 678" class="{{ $field }}">
                <span class="text-[13px] font-normal text-[var(--muted)]">{{ __('We call or WhatsApp you on this number.') }}</span>
                @error('phone') <span class="{{ $error }}">{{ $message }}</span> @enderror
            </label>

            {{-- Honeypot: hidden from people. --}}
            <div class="hidden" aria-hidden="true">
                <label for="rb-website">Website</label>
                <input id="rb-website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            @if ($kind === 'stay' && $total)
                <div class="flex justify-between border-t border-[var(--line)] pt-3 text-base">
                    <span>{{ trans_choice(':count night|:count nights', $nights) }} × {{ $unit->rent_currency }} {{ number_format($unit->nightly_rate / 100, 0, '.', ',') }}</span>
                    <b class="tnum">{{ $unit->rent_currency }} {{ $total }}</b>
                </div>
            @endif

            <button type="submit" wire:loading.attr="disabled"
                    class="flex min-h-[52px] items-center justify-center rounded-xl text-base font-bold text-white disabled:opacity-60" style="background-color: var(--brand);">
                <span wire:loading.remove wire:target="submit">{{ $kind === 'stay' ? __('Request booking') : __('Send enquiry') }}</span>
                <span wire:loading wire:target="submit">{{ __('Sending…') }}</span>
            </button>
        </form>

        @if ($whatsapp)
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener"
               class="flex min-h-[52px] items-center justify-center gap-2 rounded-xl border border-[var(--ink)] text-base font-bold text-[var(--ink)]">
                <x-cms.whatsapp-icon class="h-5 w-5" />
                {{ $kind === 'stay' ? __('Book on WhatsApp') : __('Ask on WhatsApp') }}
            </a>
        @endif
        <p class="text-center text-sm text-[var(--muted)]">{{ __('No payment now. The front desk confirms by phone.') }}</p>
    @endif
</div>
