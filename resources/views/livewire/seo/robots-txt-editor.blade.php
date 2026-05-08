<div class="space-y-4">
    <x-ui.card>
        <x-ui.card-header>
            <div>
                <x-ui.card-title>robots.txt</x-ui.card-title>
                <x-ui.card-description>Controls how search crawlers and bots access the whole site.</x-ui.card-description>
            </div>
            @if ($exists)
                <x-ui.badge variant="success">Exists</x-ui.badge>
            @else
                <x-ui.badge variant="warning">Not created</x-ui.badge>
            @endif
        </x-ui.card-header>

        <div class="mb-4 flex flex-wrap gap-2">
            <flux:button wire:click="usePreset('allow_all')" variant="ghost" size="sm">Allow all</flux:button>
            <flux:button wire:click="usePreset('block_ai')" variant="ghost" size="sm">Block AI bots</flux:button>
            <flux:button wire:click="usePreset('block_all')" variant="ghost" size="sm">Block all</flux:button>
        </div>

        <flux:textarea wire:model="content" rows="12" class="font-mono text-sm" spellcheck="false" />

        <div class="mt-4">
            <flux:button wire:click="save" variant="primary">Save &amp; Push</flux:button>
        </div>
    </x-ui.card>
</div>
