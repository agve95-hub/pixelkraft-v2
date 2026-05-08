<x-layouts.app>
    <x-slot:title>Products — {{ $site->name }}</x-slot:title>

    <div class="max-w-4xl space-y-5">
        <div class="pk-page-head">
            <div>
                <h1 class="pk-page-title">Product Listings</h1>
                <p class="pk-page-sub">{{ $site->name }}</p>
            </div>
            <x-ui.button href="{{ route('products.create', $site) }}" icon="plus" size="sm">New Product</x-ui.button>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
            @forelse ($products as $product)
                <x-ui.card>
                    @if (!empty($product->images))
                        <div class="-mx-[18px] -mt-4 mb-3 overflow-hidden rounded-t-[10px]">
                            <img src="{{ $product->images[0] }}" alt="{{ $product->name }}" class="h-32 w-full object-cover">
                        </div>
                    @endif
                    <x-ui.card-header>
                        <div>
                            <x-ui.card-title>{{ $product->name }}</x-ui.card-title>
                        </div>
                        @switch($product->status)
                            @case('active') <x-ui.badge variant="success">Active</x-ui.badge> @break
                            @case('archived') <x-ui.badge>Archived</x-ui.badge> @break
                            @default <x-ui.badge variant="warning">Draft</x-ui.badge>
                        @endswitch
                    </x-ui.card-header>
                    <p class="font-mono text-sm">{{ $product->formattedPrice() }}</p>
                </x-ui.card>
            @empty
                <div class="col-span-full">
                    <x-ui.empty icon="cube" title="No products yet" description="Create your first product listing for {{ $site->name }}." />
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.app>
