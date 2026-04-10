<x-layouts.app>
    <x-slot:title>{{ $site->name }} — Invoices</x-slot:title>

    @livewire('sites.invoice-manager', ['siteId' => $site->id], key('invoices-' . $site->id))
</x-layouts.app>
