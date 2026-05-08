<x-layouts.app>
    <x-slot:title>Analytics</x-slot:title>

    <div class="space-y-5">
        <div class="pk-page-head">
            <div>
                <h1 class="pk-page-title">Analytics</h1>
                <p class="pk-page-sub">Track traffic, speed, runtime, downtime, and user activity across your sites.</p>
            </div>
            <x-ui.button-group>
                <x-ui.button href="{{ route('dashboard') }}" variant="outline" size="sm" icon="home">Dashboard</x-ui.button>
                <x-ui.button href="{{ route('sites.index') }}" variant="outline" size="sm" icon="globe-alt">Sites</x-ui.button>
            </x-ui.button-group>
        </div>

        @livewire('analytics.unified-dashboard')
    </div>
</x-layouts.app>
