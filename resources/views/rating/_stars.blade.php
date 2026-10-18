{{-- Five radio stars; pure CSS, so it works without JavaScript. --}}
@php $current = (int) old('score', $score ?? 0); @endphp
<fieldset class="pms-stars" aria-label="{{ __('Your rating') }}">
    @for ($i = 5; $i >= 1; $i--)
        <input type="radio" id="score-{{ $i }}" name="score" value="{{ $i }}" @checked($current === $i) required>
        <label for="score-{{ $i }}" title="{{ trans_choice(':count star|:count stars', $i) }}">★</label>
    @endfor
</fieldset>
<style>
    .pms-stars { display: inline-flex; flex-direction: row-reverse; gap: .25rem; border: 0; padding: 0; margin: 0; }
    .pms-stars input { position: absolute; opacity: 0; width: 1px; height: 1px; }
    .pms-stars label { font-size: 2.5rem; line-height: 1; color: #d4d4d8; cursor: pointer; transition: color .1s, transform .1s; }
    .pms-stars label:hover, .pms-stars label:hover ~ label,
    .pms-stars input:checked ~ label { color: #f59e0b; }
    .pms-stars input:focus-visible + label { outline: 2px solid var(--brand, #0f766e); outline-offset: 2px; border-radius: .25rem; }
    .pms-stars label:active { transform: scale(.92); }
</style>
