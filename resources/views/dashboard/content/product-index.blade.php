<x-layouts.app>
    <x-slot:title>Products — {{ $site->name }}</x-slot:title>

    <div class="max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="xl">Product Listings</flux:heading>
                <flux:subheading>{{ $site->name }}</flux:subheading>
            </div>
            <flux:button href="{{ route('products.create', $site) }}" variant="primary" icon="plus" size="sm">New Product</flux:button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($products as $product)
                <flux:card>
                    @if (!empty($product->images))
                        <div class="rounded-lg overflow-hidden mb-3">
                            <img src="{{ $product->images[0] }}" alt="{{ $product->name }}" class="w-full h-32 object-cover">
                        </div>
                    @endif
                    <flux:heading size="sm">{{ $product->name }}</flux:heading>
                    <div class="flex items-center justify-between mt-2">
                        <flux:text class="font-mono">{{ $product->formattedPrice() }}</flux:text>
                        @switch($product->status)
                            @case('active') <flux:badge size="sm" color="lime">Active</flux:badge> @break
                            @case('archived') <flux:badge size="sm" color="zinc">Archived</flux:badge> @break
                            @default <flux:badge size="sm" color="yellow">Draft</flux:badge>
                        @endswitch
                    </div>
                </flux:card>
            @empty
                <div class="col-span-full">
                    <flux:card class="py-12 text-center">
                        <flux:subheading>No products yet.</flux:subheading>
                    </flux:card>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.app>
