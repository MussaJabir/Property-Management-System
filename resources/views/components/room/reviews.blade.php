@props(['summary', 'reviews'])

{{-- Approved reviews of one room: the score, then up to four, then the rest. --}}
@if ($summary)
    <section id="reviews" x-data="{ all: false }" class="flex scroll-mt-24 flex-col gap-5 border-t border-[var(--line)] pt-7">
        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
            <h2 class="inline-flex items-center gap-2 text-[22px] font-bold">
                <x-cms.star class="h-5 w-5" />
                {{ number_format($summary['average'], 1) }} · {{ trans_choice(':count review|:count reviews', $summary['count']) }}
            </h2>
            <span class="text-sm text-[var(--muted)]">{{ __('From people who stayed. Checked by the owner before they appear.') }}</span>
        </div>
        @if ($reviews->isNotEmpty())
            <div class="grid grid-cols-[repeat(auto-fill,minmax(280px,1fr))] gap-x-10 gap-y-7">
                @foreach ($reviews as $i => $review)
                    <article @if ($i >= 4) x-show="all" x-cloak @endif class="flex flex-col gap-2">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-[#f1efea] text-[15px] font-bold">{{ mb_substr($review->publicName(), 0, 1) }}</span>
                            <span class="flex flex-col">
                                <b class="text-[15px]">{{ $review->publicName() }}</b>
                                <span class="text-[13px] text-[var(--muted)]">{{ $review->created_at?->translatedFormat('F Y') }}</span>
                            </span>
                        </div>
                        <span class="text-[13px] tracking-[2px]" aria-label="{{ __(':score out of 5 stars', ['score' => $review->score]) }}">{{ str_repeat('★', $review->score) }}{{ str_repeat('☆', 5 - $review->score) }}</span>
                        <p class="whitespace-pre-line text-[15px] leading-relaxed text-[#3d3b37]">{{ $review->comment }}</p>
                    </article>
                @endforeach
            </div>
            @if ($reviews->count() > 4)
                <button type="button" x-show="! all" @click="all = true"
                        class="min-h-[44px] self-start rounded-xl border border-[var(--ink)] px-4 text-[15px] font-semibold">
                    {{ __('Show all :count reviews', ['count' => $reviews->count()]) }}
                </button>
            @endif
        @endif
    </section>
@endif
