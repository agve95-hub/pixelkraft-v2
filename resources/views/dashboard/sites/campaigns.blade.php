<x-layouts.app>
    <x-slot:title>{{ $site->name }} — Campaigns &amp; Announcements</x-slot:title>

    @livewire('sites.campaign-manager', ['siteId' => $site->id], key('campaigns-' . $site->id))
</x-layouts.app>
