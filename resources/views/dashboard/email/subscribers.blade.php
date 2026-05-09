<x-layouts.app>
    <x-slot:title>Subscribers — {{ isset($site) ? $site->name : 'All Sites' }}</x-slot:title>
    <div class="space-y-5">
        <div class="ui-page-head">
            <div>
                @isset($site)
                    <a href="{{ route('sites.show', $site) }}" class="back-link">
                        <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                    </a>
                @endisset
                <h1 class="ui-page-title">Subscribers</h1>
                <p class="ui-page-sub">{{ isset($site) ? 'Newsletter subscribers for '.$site->name.'.' : 'Manage subscriber lists across all your sites.' }}</p>
            </div>
        </div>
        @livewire('email.subscriber-list', ['siteId' => $site->id ?? null])
    </div>
</x-layouts.app>
