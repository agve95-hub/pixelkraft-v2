<div
    class="flex flex-col h-[calc(100vh-3.5rem)]"
    x-data="editorState({
        previewRegions: @js($previewRegions),
        selectedRegionId: @js($selectedRegion?->id),
    })"
    x-on:highlight-region.window="highlightRegion($event.detail.selector)"
    x-on:reload-iframe.window="reloadIframe()"
>
    {{-- Toolbar --}}
    <div class="flex items-center gap-2 border-b border-zinc-800 px-4 py-2 bg-zinc-900/50 flex-shrink-0">
        {{-- Back --}}
        <a href="{{ route('sites.show', $site) }}" class="flux-btn-ghost text-xs !px-2 !py-1.5">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
        </a>

        {{-- Page info --}}
        <div class="flex items-center gap-2 mr-auto min-w-0">
            <span class="text-sm font-medium text-zinc-200 truncate">{{ $page->title ?? $page->file_path }}</span>
            <span class="mono text-xs text-zinc-600 truncate hidden sm:inline">{{ $page->url_path }}</span>
        </div>

        {{-- Mode toggle --}}
        <div class="flex items-center rounded-lg border border-zinc-700 bg-zinc-800 p-0.5">
            <button
                wire:click="setMode('visual')"
                @class([
                    'px-3 py-1 rounded-md text-xs font-medium transition',
                    'bg-violet-600 text-white' => $mode === 'visual',
                    'text-zinc-400 hover:text-zinc-200' => $mode !== 'visual',
                ])
            >
                {{ $editorProfile['visual_editing_supported'] ? 'Visual' : 'Preview' }}
            </button>
            <button
                wire:click="setMode('code')"
                @class([
                    'px-3 py-1 rounded-md text-xs font-medium transition',
                    'bg-violet-600 text-white' => $mode === 'code',
                    'text-zinc-400 hover:text-zinc-200' => $mode !== 'code',
                ])
            >
                Code
            </button>
        </div>

        {{-- Save --}}
        <button
            wire:click="openSaveModal"
            class="flux-btn-primary text-xs !py-1.5"
            wire:loading.attr="disabled"
            wire:target="openSaveModal,save"
            @disabled($mode === 'visual' && ! $selectedRegionEditable)
        >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" /></svg>
            Save & Push
        </button>
    </div>

    {{-- Main editor area --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- ── Visual Mode: iframe ─────────────── --}}
        @if ($mode === 'visual')
            <div class="flex-1 relative bg-zinc-950">
                <div class="absolute left-4 right-4 top-4 z-10 rounded-lg border border-violet-500/20 bg-zinc-950/85 px-4 py-3 text-sm text-zinc-100 shadow-lg backdrop-blur">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="font-semibold">{{ $editorProfile['visual_notice'] }}</span>
                        @if ($editorProfile['visual_editing_supported'])
                            <span class="text-zinc-400">{{ $patchableRegionCount }} of {{ $previewRegionCount }} detected regions can be saved visually.</span>
                        @else
                            <span class="text-zinc-400">{{ $previewRegionCount }} detected regions are available for inspection in the preview.</span>
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-zinc-400">
                        {{ $editorProfile['visual_hint'] }}
                    </p>
                </div>

                {{-- Iframe --}}
                <iframe
                    x-ref="previewFrame"
                    src="{{ $previewUrl }}"
                    class="w-full h-full border-0"
                    sandbox="allow-same-origin allow-scripts"
                    x-on:load="onIframeLoad()"
                    x-on:error="iframeLoading = false"
                ></iframe>

                {{-- Overlay loading state --}}
                <div
                    x-show="iframeLoading"
                    class="absolute inset-0 flex items-center justify-center bg-zinc-950/80"
                >
                    <div class="flex items-center gap-3 text-zinc-400">
                        <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Loading preview...
                    </div>
                </div>
            </div>

            {{-- ── Inline edit panel (when region selected) ── --}}
            @if ($selectedRegion)
                <div class="w-80 border-l border-zinc-800 bg-zinc-900 flex flex-col flex-shrink-0">
                    <div class="px-4 py-3 border-b border-zinc-800">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider">
                                {{ $selectedRegionEditable ? 'Edit Region' : 'Preview Region' }}
                            </h3>
                            <button
                                wire:click="$set('selectedRegionId', null)"
                                class="text-zinc-600 hover:text-zinc-400"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="flux-badge-purple !text-[10px]">{{ $selectedRegion->region_type }}</span>
                            @if ($selectedRegionEditable)
                                <span class="flux-badge-green !text-[10px]">visual save</span>
                            @else
                                <span class="flux-badge-amber !text-[10px]">code mode</span>
                            @endif
                            <span class="mono text-[10px] text-zinc-600 truncate">{{ $selectedRegion->selector }}</span>
                        </div>
                    </div>

                    <div class="flex-1 p-4 overflow-y-auto">
                        @if (! $selectedRegionEditable)
                            <div class="space-y-4 text-sm text-zinc-400">
                                @if ($editorProfile['visual_editing_supported'])
                                    <p>This region was detected correctly in the preview, but pixelkraft could not map it back to one unique source edit safely.</p>
                                    <p>That usually means the content is split across several elements or reused in multiple places.</p>
                                    <p class="text-zinc-300">Try clicking the exact highlighted word, span, button label, or paragraph you want to change. Smaller inner regions can often be saved visually even when the whole block cannot.</p>
                                @else
                                    <p>This preview helps you find the right content, but pixelkraft will not rewrite this component source from Visual mode.</p>
                                    <p>Use the detected content below as a guide, then switch to <span class="font-semibold text-zinc-200">Code</span> mode to update the source file directly.</p>
                                @endif

                                <div class="rounded-lg border border-zinc-800 bg-zinc-950/60 p-3">
                                    <p class="text-[11px] uppercase tracking-wider text-zinc-500">Detected Content</p>
                                    <p class="mt-2 text-sm text-zinc-200">{{ $editContent ?: '(empty)' }}</p>
                                </div>

                                <button
                                    wire:click="setMode('code')"
                                    class="flux-btn-secondary w-full text-sm"
                                >
                                    Open In Code Mode
                                </button>
                            </div>
                        @elseif ($selectedRegion->region_type === 'image')
                            {{-- Image edit --}}
                            <div class="space-y-3">
                                <label class="flux-label">Image URL</label>
                                <input
                                    type="text"
                                    wire:model.live="editContent"
                                    class="flux-input mono text-xs"
                                    placeholder="https://..."
                                >
                                @if ($editContent)
                                    <div class="rounded-lg border border-zinc-800 overflow-hidden">
                                        <img src="{{ $editContent }}" alt="Preview" class="w-full h-auto">
                                    </div>
                                @endif
                            </div>
                        @elseif ($selectedRegion->region_type === 'link')
                            {{-- Link edit --}}
                            <div class="space-y-3">
                                <label class="flux-label">URL or Text</label>
                                <input
                                    type="text"
                                    wire:model.live="editContent"
                                    class="flux-input text-sm"
                                >
                            </div>
                        @else
                            {{-- Text edit (rich text) --}}
                            <div class="space-y-3">
                                <label class="flux-label">Content</label>
                                <textarea
                                    wire:model.live="editContent"
                                    rows="8"
                                    class="flux-input text-sm resize-y"
                                ></textarea>
                            </div>
                        @endif
                    </div>

                    {{-- Region edit footer --}}
                    <div class="px-4 py-3 border-t border-zinc-800">
                        @if ($selectedRegionEditable)
                            <button wire:click="openSaveModal" class="flux-btn-primary w-full text-xs">
                                Save & Push
                            </button>
                        @endif
                    </div>
                </div>
            @else
                <div class="w-80 border-l border-zinc-800 bg-zinc-900 flex flex-col flex-shrink-0">
                    <div class="px-4 py-4 border-b border-zinc-800">
                        <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider">Visual Editing</h3>
                    </div>

                    <div class="p-4 space-y-3 text-sm text-zinc-400">
                        <p>{{ $editorProfile['visual_editing_supported'] ? 'Click a highlighted region in the preview or choose one from the region list to start editing.' : 'Click a highlighted region in the preview or choose one from the region list to inspect it.' }}</p>
                        <p>{{ $editorProfile['visual_hint'] }}</p>
                    </div>
                </div>
            @endif

        @else
            {{-- ── Code Mode ───────────────────── --}}
            <div class="flex-1 flex flex-col bg-zinc-950">
                {{-- File path header --}}
                <div class="flex items-center gap-2 px-4 py-2 border-b border-zinc-800 bg-zinc-900/30">
                    <svg class="h-4 w-4 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    <span class="mono text-xs text-zinc-400">{{ $codeFilePath }}</span>
                </div>

                {{-- Code editor textarea (CodeMirror in Phase 3.3) --}}
                <div class="flex-1 overflow-hidden">
                    <textarea
                        wire:model.live.debounce.500ms="codeContent"
                        class="w-full h-full bg-zinc-950 text-zinc-200 mono text-sm p-4 resize-none border-0 focus:outline-none focus:ring-0"
                        spellcheck="false"
                    ></textarea>
                </div>
            </div>
        @endif

        {{-- ── Region Sidebar (always visible) ── --}}
        <div class="w-72 border-l border-zinc-800 bg-zinc-900 flex-shrink-0 hidden xl:flex xl:flex-col">
            @livewire('editor.region-panel', ['pageId' => $pageId], key('region-panel'))
        </div>
    </div>

    {{-- ── Save Modal ────────────────────────── --}}
    @if ($showSaveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" x-on:keydown.escape.window="$wire.set('showSaveModal', false)">
            <div class="w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl" x-on:click.outside="$wire.set('showSaveModal', false)">
                <h3 class="text-sm font-semibold text-zinc-200 mb-4">Save & Push to GitHub</h3>

                <div class="space-y-4">
                    <div>
                        <label class="flux-label">Commit message</label>
                        <input
                            type="text"
                            wire:model="commitMessage"
                            class="flux-input text-sm"
                            placeholder="Update content..."
                            wire:keydown.enter="save"
                        >
                    </div>

                    <label class="flex items-start gap-3 rounded-lg border border-zinc-800 px-3 py-2.5 text-sm text-zinc-300">
                        <input
                            type="checkbox"
                            wire:model.live="deployAfterSave"
                            class="mt-0.5 h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-violet-500 focus:ring-violet-500"
                        >
                        <span>
                            Auto-deploy after push
                            <span class="block text-xs text-zinc-500">Recommended so your change appears online immediately.</span>
                        </span>
                    </label>

                    <div class="flex items-center gap-3">
                        <button
                            wire:click="save"
                            class="flux-btn-primary text-sm"
                            wire:loading.attr="disabled"
                            wire:target="save"
                        >
                            <span wire:loading.remove wire:target="save">
                                {{ $deployAfterSave ? 'Commit, Push & Deploy' : 'Commit & Push' }}
                            </span>
                            <span wire:loading wire:target="save" class="flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Pushing...
                            </span>
                        </button>
                        <button wire:click="$set('showSaveModal', false)" class="flux-btn-ghost text-sm">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- ── Alpine.js editor state ────────────────── --}}
@script
<script>
Alpine.data('editorState', ({ previewRegions, selectedRegionId }) => ({
    iframeLoading: true,
    previewRegions,
    selectedRegionId,
    hoveredRegionElement: null,
    tooltip: null,

    onIframeLoad() {
        this.iframeLoading = false;
        this.injectOverlayScript();
    },

    injectOverlayScript() {
        const iframe = this.$refs.previewFrame;
        if (!iframe || !iframe.contentDocument) return;

        const doc = iframe.contentDocument;

        const style = doc.createElement('style');
        style.textContent = `
            [data-pk-region] {
                transition: outline-color 120ms ease, background-color 120ms ease;
            }
            [data-pk-region][data-pk-editable="true"] {
                cursor: text !important;
            }
            [data-pk-region][data-pk-editable="false"] {
                cursor: not-allowed !important;
            }
            [data-pk-hover][data-pk-editable="true"] {
                outline: 2px dashed rgba(139, 92, 246, 0.5) !important;
                outline-offset: 2px !important;
                background: rgba(139, 92, 246, 0.08) !important;
            }
            [data-pk-hover][data-pk-editable="false"] {
                outline: 2px dashed rgba(245, 158, 11, 0.7) !important;
                outline-offset: 2px !important;
                background: rgba(245, 158, 11, 0.08) !important;
            }
            [data-pk-selected][data-pk-editable="true"] {
                outline: 2px solid rgba(139, 92, 246, 0.9) !important;
                outline-offset: 2px !important;
                background: rgba(139, 92, 246, 0.12) !important;
            }
            [data-pk-selected][data-pk-editable="false"] {
                outline: 2px solid rgba(245, 158, 11, 0.95) !important;
                outline-offset: 2px !important;
                background: rgba(245, 158, 11, 0.12) !important;
            }
            .pk-tooltip {
                position: fixed;
                background: #18181b;
                border: 1px solid #3f3f46;
                color: #e4e4e7;
                font-family: 'DM Mono', monospace;
                font-size: 11px;
                padding: 5px 8px;
                border-radius: 6px;
                pointer-events: none;
                z-index: 99999;
                white-space: nowrap;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.35);
            }
        `;
        (doc.head || doc.documentElement).appendChild(style);

        this.tooltip = doc.createElement('div');
        this.tooltip.className = 'pk-tooltip';
        this.tooltip.style.display = 'none';
        doc.body.appendChild(this.tooltip);

        this.decoratePreviewRegions(doc);

        doc.addEventListener('click', (e) => {
            const regionElement = this.findRegionElement(e.target);
            const link = (e.target?.nodeType === 1 ? e.target : e.target?.parentElement)?.closest('a') ?? null;

            if (link) {
                e.preventDefault();
            }

            if (!regionElement) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            this.selectRegionElement(regionElement, true);
        }, true);

        doc.addEventListener('mousemove', (e) => {
            const regionElement = this.findRegionElement(e.target);

            if (!regionElement) {
                this.clearHoveredRegion();
                return;
            }

            if (this.hoveredRegionElement && this.hoveredRegionElement !== regionElement) {
                this.hoveredRegionElement.removeAttribute('data-pk-hover');
            }

            this.hoveredRegionElement = regionElement;

            if (!regionElement.hasAttribute('data-pk-selected')) {
                regionElement.setAttribute('data-pk-hover', '');
            }

            this.showTooltip(regionElement);
        }, true);

        doc.addEventListener('mouseleave', () => {
            this.clearHoveredRegion();
        }, true);
    },

    highlightRegion(selector) {
        const iframe = this.$refs.previewFrame;
        if (!iframe || !iframe.contentDocument) return;

        const doc = iframe.contentDocument;

        // Clear previous
        doc.querySelectorAll('[data-pk-selected]').forEach(n => n.removeAttribute('data-pk-selected'));

        try {
            const el = doc.querySelector(selector);
            if (el) {
                this.selectRegionElement(el, false);
            }
        } catch (e) {
            // Invalid selector, ignore
        }
    },

    decoratePreviewRegions(doc) {
        const seenRegionIds = new Set();
        this.previewRegions.forEach((region) => {
            if (seenRegionIds.has(region.id)) {
                return;
            }

            seenRegionIds.add(region.id);
            try {
                const elements = doc.querySelectorAll(region.selector);

                elements.forEach((element) => {
                    if (!(element instanceof HTMLElement)) {
                        return;
                    }

                    element.setAttribute('data-pk-region', '');
                    element.setAttribute('data-pk-region-id', region.id);
                    element.setAttribute('data-pk-editable', region.editable ? 'true' : 'false');
                    element.setAttribute('data-pk-region-type', region.type);
                    element.setAttribute('data-pk-region-label', region.content || region.type);
                });
            } catch (e) {
                // Invalid selector, ignore
            }
        });

        if (this.selectedRegionId) {
            this.applySelectedRegion();
        }
    },

    findRegionElement(target) {
        let current = target?.nodeType === 1 ? target : target?.parentElement;

        while (current && current !== current.ownerDocument?.documentElement) {
            if (current.hasAttribute('data-pk-region-id')) {
                return current;
            }

            current = current.parentElement;
        }

        return null;
    },

    clearHoveredRegion() {
        if (this.hoveredRegionElement) {
            this.hoveredRegionElement.removeAttribute('data-pk-hover');
            this.hoveredRegionElement = null;
        }

        if (this.tooltip) {
            this.tooltip.style.display = 'none';
        }
    },

    showTooltip(element) {
        if (!this.tooltip) {
            return;
        }

        const label = element.getAttribute('data-pk-region-label') || element.getAttribute('data-pk-region-type') || 'region';
        const mode = element.getAttribute('data-pk-editable') === 'true' ? 'visual save' : 'code mode';
        this.tooltip.textContent = `${label} - ${mode}`;
        this.tooltip.style.display = 'block';

        const rect = element.getBoundingClientRect();
        this.tooltip.style.left = Math.min(rect.left, element.ownerDocument.documentElement.clientWidth - 260) + 'px';
        this.tooltip.style.top = Math.max(0, rect.top - 32) + 'px';
    },

    selectRegionElement(element, notifyLivewire = true) {
        const iframe = this.$refs.previewFrame;
        const doc = iframe?.contentDocument;

        if (!doc || !(element instanceof HTMLElement)) {
            return;
        }

        this.clearHoveredRegion();
        doc.querySelectorAll('[data-pk-selected]').forEach((node) => node.removeAttribute('data-pk-selected'));
        element.setAttribute('data-pk-selected', '');
        this.selectedRegionId = element.getAttribute('data-pk-region-id');
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });

        if (notifyLivewire && this.selectedRegionId) {
            this.$wire.onRegionSelected(this.selectedRegionId);
        }
    },

    applySelectedRegion() {
        if (!this.selectedRegionId) {
            return;
        }

        const iframe = this.$refs.previewFrame;
        const doc = iframe?.contentDocument;

        if (!doc) {
            return;
        }

        const element = doc.querySelector(`[data-pk-region-id="${this.selectedRegionId}"]`);

        if (element) {
            this.selectRegionElement(element, false);
        }
    },

    reloadIframe() {
        this.iframeLoading = true;
        const iframe = this.$refs.previewFrame;
        if (!iframe) return;

        const url = new URL(iframe.src, window.location.origin);
        url.searchParams.set('_pk_preview', Date.now().toString());
        iframe.src = url.toString();
    },
}));
</script>
@endscript
