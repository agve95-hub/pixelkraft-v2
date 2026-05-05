<x-layouts.app>
    <x-slot:title>Edit Product — {{ $site->name }}</x-slot:title>

    @livewire('content.product-editor', ['siteId' => $site->id, 'productId' => $product->id])
</x-layouts.app>
