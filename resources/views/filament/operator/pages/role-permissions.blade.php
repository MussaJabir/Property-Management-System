<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Choose what each role can do in your workspace. <strong>Owner</strong> always has full access.
        Changes apply immediately to everyone with that role.
    </p>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit">
                Save permissions
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
