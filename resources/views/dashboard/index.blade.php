<x-layouts.app>
    <x-slot:title>Dashboard</x-slot:title>

    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Dashboard</flux:heading>
                <flux:text class="mt-2 text-base">Welcome back, {{ auth()->user()->name }}</flux:text>
            </div>
            <flux:button href="{{ route('sites.create') }}" variant="primary" icon="plus">Add site</flux:button>
        </div>

        <flux:separator variant="subtle" />

        @php
            $totalSites = \App\Models\Site::count();
            $totalPages = \App\Models\Page::count();
            $totalDeploys = \App\Models\DeployLog::where('status', 'success')->count();
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <flux:card>
                <flux:heading size="sm">Total Sites</flux:heading>
                <div class="mt-2 flex items-baseline gap-2">
                    <flux:heading size="xl" class="!text-3xl tabular-nums">{{ $totalSites }}</flux:heading>
                    <flux:badge size="sm" color="lime">Active</flux:badge>
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="sm">Total Pages</flux:heading>
                <div class="mt-2 flex items-baseline gap-2">
                    <flux:heading size="xl" class="!text-3xl tabular-nums">{{ $totalPages }}</flux:heading>
                    <flux:text size="sm">across all sites</flux:text>
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="sm">Successful Deploys</flux:heading>
                <div class="mt-2 flex items-baseline gap-2">
                    <flux:heading size="xl" class="!text-3xl tabular-nums">{{ $totalDeploys }}</flux:heading>
                    <flux:text size="sm">total</flux:text>
                </div>
            </flux:card>
        </div>

        <div>
            <flux:heading size="lg" class="mb-4">Your Sites</flux:heading>
            @livewire('dashboard.site-list')
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @livewire('dashboard.activity-feed')
            @livewire('dashboard.alerts-panel')
        </div>
    </div>
</x-layouts.app>
