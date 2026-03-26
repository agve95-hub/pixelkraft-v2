<x-layouts.app>
    <x-slot:title>Dashboard</x-slot:title>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">Dashboard</flux:heading>
            <div class="flex items-center gap-2">
                @livewire('layout.notification-bell')
                <flux:button href="{{ route('sites.create') }}" variant="primary" icon="plus" size="sm">Add site</flux:button>
            </div>
        </div>

        @livewire('dashboard.site-list')

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @livewire('dashboard.activity-feed')
            @livewire('dashboard.alerts-panel')
        </div>
    </div>
</x-layouts.app>
