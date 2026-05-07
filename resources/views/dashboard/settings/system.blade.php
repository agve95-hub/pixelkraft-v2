<x-layouts.app>
    <x-slot:title>System Diagnostics</x-slot:title>

    <div class="space-y-6">
        <div class="pk-page-head">
            <div>
                <x-ui.breadcrumb>
                    <a href="{{ route('settings') }}">Settings</a>
                    <span>/</span>
                    <span>System diagnostics</span>
                </x-ui.breadcrumb>
                <h1 class="pk-page-title mt-3">System diagnostics</h1>
                <p class="pk-page-sub">Queue health, failed jobs, stale deploys, and runtime checks.</p>
            </div>
            <x-ui.button href="{{ route('system.ui') }}" variant="outline" size="sm" icon="swatch">UI system</x-ui.button>
        </div>

        @livewire('settings.system-diagnostics')
    </div>
</x-layouts.app>
