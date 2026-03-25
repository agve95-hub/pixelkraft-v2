<x-layouts.app>
    <x-slot:title>Sites</x-slot:title>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100">All Sites</h2>
                <p class="text-sm text-zinc-500">Manage your websites</p>
            </div>
            <a href="{{ route('sites.create') }}" class="btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add site
            </a>
        </div>

        @livewire('dashboard.site-list')
    </div>
</x-layouts.app>
