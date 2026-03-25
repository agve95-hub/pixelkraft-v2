<x-layouts.app>
    <x-slot:title>Templates — {{ $site->name }}</x-slot:title>

    @livewire('content.template-manager', ['siteId' => $site->id])
</x-layouts.app>
