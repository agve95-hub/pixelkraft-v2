<x-layouts.app>
    <x-slot:title>Settings — {{ $site->name }}</x-slot:title>
    <div class="space-y-5">
        <div class="ui-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="ui-page-title">Site Settings</h1>
                <p class="ui-page-sub">Domain, deployment, integrations, and API tokens for {{ $site->name }}.</p>
            </div>
        </div>
        @livewire('sites.site-settings', ['siteId' => $site->id])
    </div>
</x-layouts.app>
