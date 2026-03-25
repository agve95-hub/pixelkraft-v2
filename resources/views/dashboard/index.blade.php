<x-layouts.app>
    <x-slot:title>Dashboard</x-slot:title>

    <div class="space-y-6">

        {{-- Quick Actions --}}
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('sites.create') }}" class="btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add site
            </a>
            <button class="btn-secondary" disabled>
                Deploy all
            </button>
        </div>

        {{-- Stats Grid --}}
        @livewire('dashboard.site-list')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Activity Feed --}}
            @livewire('dashboard.activity-feed')

            {{-- Alerts Panel --}}
            @livewire('dashboard.alerts-panel')
        </div>

    </div>
</x-layouts.app>
