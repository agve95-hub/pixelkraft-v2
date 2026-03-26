<div class="space-y-4">
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-zinc-200">robots.txt</h3>
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
            <button wire:click="usePreset('allow_all')" class="flux-btn-ghost text-[10px] !px-2 !py-1">Allow all</button>
            <button wire:click="usePreset('block_ai')" class="flux-btn-ghost text-[10px] !px-2 !py-1">Block AI bots</button>
            <button wire:click="usePreset('block_all')" class="flux-btn-ghost text-[10px] !px-2 !py-1">Block all</button>
        </div>

        <textarea
            wire:model="content"
            rows="12"
            class="flux-input mono text-xs resize-y"
            spellcheck="false"
        ></textarea>

        <div class="flex items-center gap-3 mt-4">
            <button wire:click="save" class="flux-btn-primary text-sm">Save & Push</button>
        </div>
    </div>
</div>
