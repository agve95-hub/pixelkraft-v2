<x-layouts.app>
    <x-slot:title>Inbox — {{ $site->name }}</x-slot:title>
    <div class="space-y-5">
        <div class="ui-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="ui-page-title">Inbox</h1>
                <p class="ui-page-sub">Contact form submissions and conversations for {{ $site->name }}.</p>
            </div>
        </div>
        @livewire('sites.site-inbox', ['siteId' => $site->id], key('inbox-' . $site->id))
    </div>
</x-layouts.app>
