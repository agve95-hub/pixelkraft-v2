<div class="space-y-4">
    <div class="card">
        <div class="mb-4 space-y-1">
            <h3 class="text-lg font-semibold text-zinc-100">JSON-LD structured data</h3>
            <p class="text-sm text-zinc-400">Pick the closest preset, then adjust only the values you want search engines to understand.</p>
        </div>

        @if (! $schemaEditingSupported)
            <div class="mb-4 rounded-2xl border border-amber-500/20 bg-amber-500/5 px-4 py-4 text-sm text-amber-100">
                {{ $schemaEditingNotice }}
            </div>
        @endif

        {{-- Presets --}}
        <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-[11px] text-zinc-500 uppercase tracking-wider leading-loose">Presets</span>
            <button wire:click="usePreset('article')" class="flux-btn-ghost text-xs !px-3 !py-1.5" @disabled(! $schemaEditingSupported)>Article</button>
            <button wire:click="usePreset('product')" class="flux-btn-ghost text-xs !px-3 !py-1.5" @disabled(! $schemaEditingSupported)>Product</button>
            <button wire:click="usePreset('local_business')" class="flux-btn-ghost text-xs !px-3 !py-1.5" @disabled(! $schemaEditingSupported)>Local Business</button>
            <button wire:click="usePreset('faq')" class="flux-btn-ghost text-xs !px-3 !py-1.5" @disabled(! $schemaEditingSupported)>FAQ</button>
        </div>

        <textarea
            wire:model="schemaJson"
            rows="16"
            class="flux-input mono text-sm resize-y"
            spellcheck="false"
            @disabled(! $schemaEditingSupported)
            placeholder='{
  "&#64;context": "https://schema.org",
  "&#64;type": "Article",
  "headline": "Your page title"
}'
        ></textarea>
        @error('schemaJson') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror

        <div class="flex items-center gap-3 mt-4">
            <button wire:click="save" class="flux-btn-primary text-sm" @disabled(! $schemaEditingSupported)>Save & Push</button>
        </div>

        <p class="text-[10px] text-zinc-600 mt-2">
                    Test your markup at <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer" class="text-violet-400 hover:text-violet-300">Google Rich Results Test</a>
        </p>
    </div>
</div>
