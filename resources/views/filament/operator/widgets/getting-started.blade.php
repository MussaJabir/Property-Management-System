<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Welcome to {{ tenant('name') ?? 'your workspace' }}
        </x-slot>
        <x-slot name="description">
            Your operator workspace is ready. Add your first property to start tracking units, renters, and rent.
        </x-slot>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">1. Add a property</div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    A building, compound, or commercial space.
                </p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Phase 3 — coming next.</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">2. Add units</div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Rooms, apartments, business frames inside each property.
                </p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Phase 3.</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-sm font-semibold text-gray-900 dark:text-white">3. Assign renters</div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Create a lease, generate invoices, record payments.
                </p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Phase 4 + 5.</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
