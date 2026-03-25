<div class="max-w-3xl">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-100">{{ $productId ? 'Edit Product' : 'New Product' }}</h2>
            <p class="text-sm text-zinc-500">Manage a product listing.</p>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model="status" class="input-field text-sm w-auto">
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="archived">Archived</option>
            </select>
            <button wire:click="save" class="btn-primary text-sm" wire:loading.attr="disabled">
                {{ $productId ? 'Update' : 'Create' }}
            </button>
        </div>
    </div>

    <div class="space-y-6">
        {{-- Basic Info --}}
        <div class="card space-y-4">
            <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider">Details</h3>

            <div>
                <label class="input-label">Name</label>
                <input type="text" wire:model="name" class="input-field" placeholder="Product name">
                @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="input-label">Description</label>
                <textarea wire:model="description" rows="5" class="input-field text-sm resize-y" placeholder="Product description... HTML supported."></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="input-label">Price</label>
                    <input type="number" step="0.01" wire:model="price" class="input-field mono" placeholder="0.00">
                </div>
                <div>
                    <label class="input-label">Currency</label>
                    <select wire:model="currency" class="input-field">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Images --}}
        <div class="card space-y-3">
            <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider">Images</h3>
            <div class="flex gap-2">
                <input type="text" wire:model="imageInput" wire:keydown.enter.prevent="addImage" class="input-field text-xs mono flex-1" placeholder="https://... image URL">
                <button wire:click="addImage" class="btn-secondary text-xs !py-1.5 !px-3">Add</button>
            </div>
            @if (!empty($images))
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($images as $index => $img)
                        <div class="relative group rounded-lg border border-zinc-800 overflow-hidden">
                            <img src="{{ $img }}" alt="" class="w-full h-24 object-cover">
                            <button wire:click="removeImage({{ $index }})" class="absolute top-1 right-1 h-5 w-5 rounded-full bg-red-600 text-white text-xs opacity-0 group-hover:opacity-100 transition flex items-center justify-center">×</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Attributes --}}
        <div class="card space-y-3">
            <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider">Attributes</h3>
            <div class="flex gap-2">
                <input type="text" wire:model="attrKey" class="input-field text-xs flex-1" placeholder="Key (e.g. Color)">
                <input type="text" wire:model="attrValue" wire:keydown.enter.prevent="addAttribute" class="input-field text-xs flex-1" placeholder="Value (e.g. Red)">
                <button wire:click="addAttribute" class="btn-secondary text-xs !py-1.5 !px-3">Add</button>
            </div>
            @if (!empty($attributes))
                <div class="space-y-1">
                    @foreach ($attributes as $key => $value)
                        <div class="flex items-center justify-between rounded bg-zinc-800/40 px-3 py-1.5">
                            <span class="text-xs"><span class="text-zinc-400 font-medium">{{ $key }}:</span> <span class="text-zinc-300">{{ $value }}</span></span>
                            <button wire:click="removeAttribute('{{ $key }}')" class="text-xs text-zinc-600 hover:text-red-400">×</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Output --}}
        <div class="card">
            <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider mb-3">Output Path</h3>
            <input type="text" wire:model="outputPath" class="input-field text-xs mono" placeholder="products/product-name.html">
            <p class="mt-1 text-[10px] text-zinc-600">Where in the repo the generated HTML file will be saved.</p>
        </div>
    </div>
</div>
