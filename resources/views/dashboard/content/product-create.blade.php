<x-layouts.app>
    <x-slot:title>New Product — {{ $site->name }}</x-slot:title>

    @livewire('content.product-editor', ['siteId' => $site->id])
</x-layouts.app>
