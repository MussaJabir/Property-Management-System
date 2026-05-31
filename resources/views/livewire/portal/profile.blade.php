<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Profile') }}</h1>

    @if ($user->must_change_password)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            {{ __('You are signed in with a temporary password. Please change it below to secure your account.') }}
        </div>
    @endif

    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">{{ __('Account details') }}</h2>
        <form wire:submit="saveProfile" class="mt-4 space-y-4">
            <div>
                <label class="block text-sm font-medium">{{ __('Name') }}</label>
                <input wire:model="name" type="text" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('Email') }}</label>
                <input wire:model="email" type="email" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('Phone') }}</label>
                <input type="text" disabled value="{{ $user->phone }}" class="mt-1 block w-full rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-800 dark:bg-zinc-950">
                <p class="mt-1 text-xs text-zinc-500">{{ __('Contact your landlord to change your phone number.') }}</p>
            </div>
            <button type="submit"
                    class="rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                    style="background-color: var(--brand);">
                {{ __('Save changes') }}
            </button>
        </form>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900 dark:ring-1 dark:ring-white/10">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500">{{ __('Change password') }}</h2>
        <form wire:submit="changePassword" class="mt-4 space-y-4">
            <div>
                <label class="block text-sm font-medium">{{ __('Current password') }}</label>
                <input wire:model="currentPassword" type="password" autocomplete="current-password" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                @error('currentPassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('New password') }}</label>
                <input wire:model="newPassword" type="password" autocomplete="new-password" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
                @error('newPassword') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('Confirm new password') }}</label>
                <input wire:model="newPasswordConfirmation" type="password" autocomplete="new-password" class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950">
            </div>
            <button type="submit"
                    class="rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                    style="background-color: var(--brand);">
                {{ __('Update password') }}
            </button>
        </form>
    </div>
</div>
