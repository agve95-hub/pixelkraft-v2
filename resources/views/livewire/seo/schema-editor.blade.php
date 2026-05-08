<div class="space-y-4">
    <x-ui.card>
        <x-ui.card-header>
            <div>
                <x-ui.card-title>JSON-LD structured data</x-ui.card-title>
                <x-ui.card-description>Pick the closest preset, then adjust only the values you want search engines to understand.</x-ui.card-description>
            </div>
        </x-ui.card-header>

        @if (! $schemaEditingSupported)
            <x-ui.alert variant="warning" icon="information-circle">{{ $schemaEditingNotice }}</x-ui.alert>
        @endif

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-[11px] uppercase tracking-wider text-zinc-500">Presets</span>
            <flux:button wire:click="usePreset('article')" variant="ghost" size="sm" @disabled(!$schemaEditingSupported)>Article</flux:button>
            <flux:button wire:click="usePreset('product')" variant="ghost" size="sm" @disabled(!$schemaEditingSupported)>Product</flux:button>
            <flux:button wire:click="usePreset('local_business')" variant="ghost" size="sm" @disabled(!$schemaEditingSupported)>Local Business</flux:button>
            <flux:button wire:click="usePreset('faq')" variant="ghost" size="sm" @disabled(!$schemaEditingSupported)>FAQ</flux:button>
        </div>

        <flux:textarea wire:model="schemaJson" rows="16" class="font-mono text-sm" spellcheck="false"
            :disabled="!$schemaEditingSupported"
            placeholder='{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "Your page title"
}' />
        <flux:error name="schemaJson" />

        <div class="mt-4 flex items-center gap-3">
            <flux:button wire:click="save" variant="primary" @disabled(!$schemaEditingSupported)>Save &amp; Push</flux:button>
        </div>

        <p class="mt-2 text-[10px] text-zinc-600">
            Test at <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer" class="text-violet-400 hover:text-violet-300">Google Rich Results Test</a>
        </p>
    </x-ui.card>
</div>
