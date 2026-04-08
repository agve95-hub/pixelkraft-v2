<x-layouts.app>
    <x-slot:title>Analytics</x-slot:title>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <flux:heading size="xl">Analytics</flux:heading>
                <flux:text class="mt-2 text-base">Track traffic, speed, runtime, downtime, and user activity across your sites.</flux:text>
            </div>
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('dashboard') }}" variant="subtle" icon="home">Back to dashboard</flux:button>
                <flux:button href="{{ route('sites.index') }}" variant="subtle" icon="globe-alt">Manage sites</flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        @livewire('analytics.unified-dashboard')
    </div>
</x-layouts.app>
