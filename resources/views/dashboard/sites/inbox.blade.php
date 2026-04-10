<x-layouts.app>
    <x-slot:title>{{ $site->name }} — Inbox</x-slot:title>

    @livewire('sites.site-inbox', ['siteId' => $site->id], key('inbox-' . $site->id))
</x-layouts.app>
