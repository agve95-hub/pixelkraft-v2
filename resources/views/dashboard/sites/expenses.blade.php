<x-layouts.app>
    <x-slot:title>{{ $site->name }} — Expenses</x-slot:title>

    @livewire('sites.expense-manager', ['siteId' => $site->id], key('expenses-' . $site->id))
</x-layouts.app>
