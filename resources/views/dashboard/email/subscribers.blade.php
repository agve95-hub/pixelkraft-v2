<x-layouts.app>
    <x-slot:title>Subscribers</x-slot:title>

    <div class="max-w-4xl">
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-zinc-100">Newsletter Subscribers</h2>
            <p class="text-sm text-zinc-500">Manage your subscriber lists across all sites.</p>
        </div>

        @livewire('email.subscriber-list')
    </div>
</x-layouts.app>
