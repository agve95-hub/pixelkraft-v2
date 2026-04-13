<x-layouts.app>
    <x-slot:title>{{ $site->name }} — Reports</x-slot:title>

    @livewire('sites.report-manager', ['siteId' => $site->id], key('reports-' . $site->id))
</x-layouts.app>
