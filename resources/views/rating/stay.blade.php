@php $client = tenant(); @endphp
<x-layouts.portal :title="__('Rate your stay')">
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl bg-white p-8 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
            <div class="mb-6 text-center">
                <x-cms.brand-mark :client="$client" size="h-12 w-12" class="mx-auto mb-3 text-lg" />
                <h1 class="text-xl font-semibold">{{ __('How was your stay?') }}</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $booking->guest?->full_name }} · {{ $client?->name }}
                    <br>{{ $booking->unit?->code }} · {{ $booking->check_in_date->format('d/m/Y') }} – {{ $booking->check_out_date->format('d/m/Y') }}
                </p>
            </div>

            @if (session('rated'))
                <div class="mb-5 rounded-lg bg-emerald-50 p-3 text-center text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                    {{ __('Asante! Thank you for your rating.') }}
                </div>
            @endif

            <form method="POST" action="{{ $action }}" class="space-y-5">
                @csrf
                <div class="text-center">
                    @include('rating._stars', ['score' => $rating?->score])
                    @error('score') <p class="mt-1 text-xs text-red-600">{{ __('Please choose 1 to 5 stars.') }}</p> @enderror
                </div>

                <div>
                    <label for="comment" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ __('Anything to tell us? (optional)') }}</label>
                    <textarea id="comment" name="comment" rows="4" maxlength="2000"
                        class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-950">{{ old('comment', $rating?->comment) }}</textarea>
                </div>

                <button type="submit" class="w-full rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90" style="background-color: var(--brand);">
                    {{ $rating ? __('Update my rating') : __('Send my rating') }}
                </button>
            </form>
        </div>
    </div>
</x-layouts.portal>
