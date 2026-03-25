<x-layouts.app>
    <x-slot:title>{{ $site->name }} — Settings</x-slot:title>

    @livewire('sites.site-settings', ['siteId' => $site->id])
</x-layouts.app>
