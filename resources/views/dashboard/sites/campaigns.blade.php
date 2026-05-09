<x-layouts.app>
    <x-slot:title>Campaigns — {{ $site->name }}</x-slot:title>
    <div class="space-y-5">
        <div class="ui-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="ui-page-title">Campaigns &amp; Announcements</h1>
                <p class="ui-page-sub">Popup campaigns and top-bar banners for {{ $site->name }}.</p>
            </div>
        </div>
        @livewire('sites.campaign-manager', ['siteId' => $site->id], key('campaigns-' . $site->id))
    </div>
</x-layouts.app>
