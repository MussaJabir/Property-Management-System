@php $client = tenant(); @endphp

<div class="mx-auto max-w-md">
    <div class="rounded-2xl bg-white p-8 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 inline-flex h-12 w-12 items-center justify-center rounded-xl text-lg font-bold text-white" style="background-color: var(--brand);">
                {{ mb_substr($client?->name ?? 'P', 0, 1) }}
            </div>
            <h1 class="text-xl font-semibold">{{ $client?->name ?? 'Portal' }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Activate your renter portal account') }}</p>
        </div>

        @if ($valid)
            <form wire:submit="submit" class="space-y-4">
                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ __('Choose a password to finish setting up your account.') }}</p>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('New password') }}</label>
                    <input wire:model="password" type="password" autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950">
                    @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Confirm password') }}</label>
                    <input wire:model="password_confirmation" type="password" autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950">
                </div>

                <button type="submit"
                        class="w-full rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                        style="background-color: var(--brand);">
                    {{ __('Activate account') }}
                </button>
            </form>
        @else
            <div class="space-y-4 text-center">
                <p class="text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('This activation link is invalid or has expired.') }}
                </p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Please contact :client to request a new activation link.', ['client' => $client?->name ?? __('your landlord')]) }}
                </p>
                <a href="/{{ $client?->slug }}/portal/login" class="inline-block text-sm font-semibold" style="color: var(--brand);">
                    {{ __('Back to sign in') }}
                </a>
            </div>
        @endif
    </div>
</div>
