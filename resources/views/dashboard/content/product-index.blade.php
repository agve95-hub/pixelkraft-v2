<x-layouts.app>
    <x-slot:title>Products — {{ $site->name }}</x-slot:title>

    <div class="max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100">Product Listings</h2>
                <p class="text-sm text-zinc-500">{{ $site->name }}</p>
            </div>
            <a href="{{ route('products.create', $site) }}" class="btn-primary text-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Product
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse ($site->productListings()->latest()->get() as $product)
                <div class="card-hover">
                    @if (!empty($product->images))
                        <div class="rounded-lg overflow-hidden mb-3 border border-zinc-800">
                            <img src="{{ $product->images[0] }}" alt="{{ $product->name }}" class="w-full h-32 object-cover">
                        </div>
                    @endif
                    <h4 class="text-sm font-medium text-zinc-100">{{ $product->name }}</h4>
                    <div class="flex items-center justify-between mt-2">
                        <span class="mono text-sm text-zinc-300">{{ $product->formattedPrice() }}</span>
                        @switch($product->status)
                            @case('active') <span class="badge-green !text-[10px]">Active</span> @break
                            @case('archived') <span class="badge bg-zinc-500/10 text-zinc-500 !text-[10px]">Archived</span> @break
                            @default <span class="badge-amber !text-[10px]">Draft</span>
                        @endswitch
                    </div>
                </div>
            @empty
                <div class="col-span-full card py-12 text-center text-sm text-zinc-500">No products yet.</div>
            @endforelse
        </div>
    </div>
</x-layouts.app>
