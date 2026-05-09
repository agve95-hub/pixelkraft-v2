<x-layouts.app>
    <x-slot:title>Maintenance — {{ $site->name }}</x-slot:title>
    <div class="space-y-5">
        <div class="ui-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="ui-page-title">Maintenance Mode</h1>
                <p class="ui-page-sub">Design a maintenance page for scheduled downtime on {{ $site->name }}.</p>
            </div>
        </div>
        @livewire('sites.maintenance-mode', ['siteId' => $site->id], key('maintenance-' . $site->id))
    </div>
</x-layouts.app>
