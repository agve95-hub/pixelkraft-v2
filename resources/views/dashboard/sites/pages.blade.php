<x-layouts.app>
    <x-slot:title>Pages — {{ $site->name }}</x-slot:title>
    <div class="space-y-5">
        <div class="ui-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="ui-page-title">Pages</h1>
                <p class="ui-page-sub">Manage, edit, and configure SEO for all pages on this site.</p>
            </div>
        </div>
        @livewire('sites.page-listing', ['siteId' => $site->id])
    </div>
</x-layouts.app>
