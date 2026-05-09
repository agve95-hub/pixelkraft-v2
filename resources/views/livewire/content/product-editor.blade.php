<div class="max-w-3xl space-y-5">
    <div class="ui-page-head">
        <div>
            <h1 class="ui-page-title">{{ $productId ? 'Edit Product' : 'New Product' }}</h1>
            <p class="ui-page-sub">Manage a product listing.</p>
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model="status" size="sm" class="w-auto">
                <flux:select.option value="draft">Draft</flux:select.option>
                <flux:select.option value="active">Active</flux:select.option>
                <flux:select.option value="archived">Archived</flux:select.option>
            </flux:select>
            <flux:button wire:click="save" variant="primary" wire:loading.attr="disabled">
                {{ $productId ? 'Update' : 'Create' }}
            </flux:button>
        </div>
    </div>

    <div class="space-y-4">
        <x-ui.card>
            <x-ui.card-header><x-ui.card-title>Details</x-ui.card-title></x-ui.card-header>
            <x-ui.card-content>
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" placeholder="Product name" />
                    <flux:error name="name" />
                </flux:field>
                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea wire:model="description" rows="5"
                        placeholder="Product description... HTML supported." />
                </flux:field>
                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Price</flux:label>
                        <flux:input type="number" step="0.01" wire:model="price"
                            placeholder="0.00" class="font-mono" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Currency</flux:label>
                        <flux:select wire:model="currency">
                            <flux:select.option value="USD">USD</flux:select.option>
                            <flux:select.option value="EUR">EUR</flux:select.option>
                            <flux:select.option value="GBP">GBP</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>
            </x-ui.card-content>
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header><x-ui.card-title>Images</x-ui.card-title></x-ui.card-header>
            <div class="flex gap-2">
                <flux:input wire:model="imageInput" wire:keydown.enter.prevent="addImage"
                    placeholder="https://... image URL" class="font-mono text-xs flex-1" size="sm" />
                <flux:button wire:click="addImage" variant="outline" size="sm">Add</flux:button>
            </div>
            @if (!empty($images))
                <div class="mt-3 grid grid-cols-3 gap-2">
                    @foreach ($images as $index => $img)
                        <div class="group relative overflow-hidden rounded-lg border border-zinc-800">
                            <img src="{{ $img }}" alt="" class="h-24 w-full object-cover">
                            <button wire:click="removeImage({{ $index }})"
                                class="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs text-white opacity-0 transition group-hover:opacity-100">&times;</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header><x-ui.card-title>Attributes</x-ui.card-title></x-ui.card-header>
            <div class="flex gap-2">
                <flux:input wire:model="attrKey" placeholder="Key (e.g. Color)" class="flex-1" size="sm" />
                <flux:input wire:model="attrValue" wire:keydown.enter.prevent="addAttribute"
                    placeholder="Value (e.g. Red)" class="flex-1" size="sm" />
                <flux:button wire:click="addAttribute" variant="outline" size="sm">Add</flux:button>
            </div>
            @if (!empty($productAttributes))
                <div class="mt-3 space-y-1">
                    @foreach ($productAttributes as $key => $value)
                        <div class="flex items-center justify-between rounded bg-zinc-800/40 px-3 py-1.5">
                            <span class="text-xs"><span class="font-medium text-zinc-400">{{ $key }}:</span> <span class="text-zinc-300">{{ $value }}</span></span>
                            <button wire:click="removeAttribute('{{ $key }}')" class="text-xs text-zinc-600 hover:text-red-400">&times;</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card>
            <x-ui.card-header><x-ui.card-title>Output Path</x-ui.card-title></x-ui.card-header>
            <flux:input wire:model="outputPath" placeholder="products/product-name.html"
                class="font-mono text-xs" />
            <p class="mt-1 text-[10px] text-zinc-600">Where in the repo the generated HTML file will be saved.</p>
        </x-ui.card>
    </div>
</div>
