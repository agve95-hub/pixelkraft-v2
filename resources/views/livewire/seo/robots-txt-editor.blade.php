<div class="space-y-4">
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-zinc-100">robots.txt</h3>
                <p class="mt-1 text-sm text-zinc-400">This file controls how search crawlers and bots are allowed to access the whole site.</p>
            </div>
            <div class="flex items-center gap-2">
                @if ($exists)
                    <span class="flux-badge-green !text-[10px]">Exists</span>
                @else
                    <span class="flux-badge-amber !text-[10px]">Not created</span>
                @endif
            </div>
        </div>

        {{-- Presets --}}
        <div class="flex flex-wrap gap-2 mb-4">
            <button wire:click="usePreset('allow_all')" class="flux-btn-ghost text-xs !px-3 !py-1.5">Allow all</button>
            <button wire:click="usePreset('block_ai')" class="flux-btn-ghost text-xs !px-3 !py-1.5">Block AI bots</button>
            <button wire:click="usePreset('block_all')" class="flux-btn-ghost text-xs !px-3 !py-1.5">Block all</button>
        </div>

        <textarea
            wire:model="content"
            rows="12"
            class="flux-input mono text-sm resize-y"
            spellcheck="false"
        ></textarea>

        <div class="flex items-center gap-3 mt-4">
            <button wire:click="save" class="flux-btn-primary text-sm">Save & Push</button>
        </div>
    </div>
</div>
