<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold">{{ __('New maintenance request') }}</h1>
    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Describe the issue so we can dispatch the right help.') }}</p>

    <form wire:submit="submit" class="mt-6 space-y-4 rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
        <div>
            <label class="block text-sm font-medium">{{ __('Title') }}</label>
            <input wire:model="title" type="text" maxlength="120"
                   class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
            @error('title') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('Unit') }}</label>
            <select wire:model="unitId" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                @foreach ($units as $u)
                    <option value="{{ $u['id'] }}">{{ $u['label'] }}</option>
                @endforeach
            </select>
            @error('unitId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('Priority') }}</label>
            <select wire:model="priority" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                <option value="low">{{ __('Low') }}</option>
                <option value="medium">{{ __('Medium') }}</option>
                <option value="high">{{ __('High') }}</option>
                <option value="urgent">{{ __('Urgent') }}</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('Description') }}</label>
            <textarea wire:model="description" rows="5"
                      class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"></textarea>
            @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">{{ __('Photos (optional)') }}</label>
            <input wire:model="photos" type="file" multiple accept="image/*"
                   class="mt-1 block w-full text-sm">
            @error('photos.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            <div wire:loading wire:target="photos" class="mt-1 text-xs text-zinc-500">{{ __('Uploading…') }}</div>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit"
                    class="rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                    style="background-color: var(--brand);">
                {{ __('Submit request') }}
            </button>
            <a href="{{ url('/'.tenant()->slug.'/portal/maintenance') }}"
               class="rounded-md border border-zinc-300 px-4 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</div>
