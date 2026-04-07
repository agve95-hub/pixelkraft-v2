<div class="space-y-4">
    <div class="card">
        <div class="mb-4 space-y-1">
            <h3 class="text-lg font-semibold text-zinc-100">JSON-LD structured data</h3>
            <p class="text-sm text-zinc-400">Pick the closest preset, then adjust only the values you want search engines to understand.</p>
        </div>

        {{-- Presets --}}
        <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-[11px] text-zinc-500 uppercase tracking-wider leading-loose">Presets</span>
            <button wire:click="usePreset('article')" class="flux-btn-ghost text-xs !px-3 !py-1.5">Article</button>
            <button wire:click="usePreset('product')" class="flux-btn-ghost text-xs !px-3 !py-1.5">Product</button>
            <button wire:click="usePreset('local_business')" class="flux-btn-ghost text-xs !px-3 !py-1.5">Local Business</button>
            <button wire:click="usePreset('faq')" class="flux-btn-ghost text-xs !px-3 !py-1.5">FAQ</button>
        </div>

        <textarea
            wire:model="schemaJson"
            rows="16"
            class="flux-input mono text-sm resize-y"
            spellcheck="false"
            placeholder='{
  "&#64;context": "https://schema.org",
  "&#64;type": "Article",
  "headline": "Your page title"
}'
        ></textarea>
        @error('schemaJson') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror

        <div class="flex items-center gap-3 mt-4">
            <button wire:click="save" class="flux-btn-primary text-sm">Save & Push</button>
        </div>

        <p class="text-[10px] text-zinc-600 mt-2">
            Test your markup at <a href="https://search.google.com/test/rich-results" target="_blank" class="text-violet-400 hover:text-violet-300">Google Rich Results Test</a>
        </p>
    </div>
</div>
