<x-layouts.app>
    <x-slot:title>Reports — {{ $site->name }}</x-slot:title>
    <div class="space-y-5">
        <div class="ui-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="ui-page-title">Client Reports</h1>
                <p class="ui-page-sub">Generate and deliver reports to {{ $site->clientDisplayName() }}.</p>
            </div>
        </div>
        @livewire('sites.report-manager', ['siteId' => $site->id], key('reports-' . $site->id))
    </div>
</x-layouts.app>
