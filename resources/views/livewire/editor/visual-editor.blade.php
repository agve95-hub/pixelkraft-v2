<div
    class="flex flex-col h-[calc(100vh-3.5rem)]"
    x-data="editorState()"
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
                wire:click="toggleMode"
                @class([
                    'px-3 py-1 rounded-md text-xs font-medium transition',
                    'bg-violet-600 text-white' => $mode === 'visual',
                    'text-zinc-400 hover:text-zinc-200' => $mode !== 'visual',
                ])
            >
                Visual
            </button>
            <button
                wire:click="toggleMode"
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
            :disabled="$wire.isSaving"
            @disabled($mode === 'visual' && ! $visualEditingEnabled)
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
                @if (! $visualEditingEnabled)
                    <div class="absolute left-4 right-4 top-4 z-10 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100 shadow-lg">
                        Visual mode is preview-only for this component-based page. Use <span class="font-semibold">Code</span> mode to edit safely.
                    </div>
                @endif

                {{-- Iframe --}}
                <iframe
                    x-ref="previewFrame"
                    src="{{ $previewUrl }}"
                    class="w-full h-full border-0"
                    sandbox="allow-same-origin allow-scripts"
                    x-on:load="onIframeLoad()"
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
            @if ($selectedRegion && $selectedRegion->isDynamic() && $visualEditingEnabled)
                <div class="w-80 border-l border-zinc-800 bg-zinc-900 flex flex-col flex-shrink-0">
                    <div class="px-4 py-3 border-b border-zinc-800">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider">Edit Region</h3>
                            <button
                                wire:click="$set('selectedRegionId', null)"
                                class="text-zinc-600 hover:text-zinc-400"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="flux-badge-purple !text-[10px]">{{ $selectedRegion->region_type }}</span>
                            <span class="mono text-[10px] text-zinc-600 truncate">{{ $selectedRegion->selector }}</span>
                        </div>
                    </div>

                    <div class="flex-1 p-4 overflow-y-auto">
                        @if ($selectedRegion->region_type === 'image')
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
                        <button wire:click="openSaveModal" class="flux-btn-primary w-full text-xs">
                            Save & Push
                        </button>
                    </div>
                </div>
            @elseif (! $visualEditingEnabled)
                <div class="w-80 border-l border-zinc-800 bg-zinc-900 flex flex-col flex-shrink-0">
                    <div class="px-4 py-4 border-b border-zinc-800">
                        <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider">Preview Only</h3>
                    </div>

                    <div class="p-4 space-y-3 text-sm text-zinc-400">
                        <p>This page is backed by component source code, so iframe clicks cannot be mapped back to safe source edits yet.</p>
                        <p>Use <span class="font-semibold text-zinc-200">Code</span> mode for reliable changes, then save and push from there.</p>
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

                    <div class="flex items-center gap-3">
                        <button
                            wire:click="save"
                            class="flux-btn-primary text-sm"
                            wire:loading.attr="disabled"
                            wire:target="save"
                        >
                            <span wire:loading.remove wire:target="save">Commit & Push</span>
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
Alpine.data('editorState', () => ({
    iframeLoading: true,
    selectedSelector: null,

    onIframeLoad() {
        this.iframeLoading = false;
        this.injectOverlayScript();
    },

    injectOverlayScript() {
        const iframe = this.$refs.previewFrame;
        if (!iframe || !iframe.contentDocument) return;

        const doc = iframe.contentDocument;

        // Inject overlay styles
        const style = doc.createElement('style');
        style.textContent = `
            [data-pk-hover] {
                outline: 2px dashed rgba(139, 92, 246, 0.5) !important;
                outline-offset: 2px !important;
                cursor: pointer !important;
            }
            [data-pk-selected] {
                outline: 2px solid rgba(139, 92, 246, 0.9) !important;
                outline-offset: 2px !important;
                background: rgba(139, 92, 246, 0.05) !important;
            }
            [data-pk-static] {
                outline: 2px dashed rgba(113, 113, 122, 0.3) !important;
                outline-offset: 2px !important;
                cursor: not-allowed !important;
            }
            .pk-tooltip {
                position: fixed;
                background: #18181b;
                border: 1px solid #3f3f46;
                color: #a1a1aa;
                font-family: 'DM Mono', monospace;
                font-size: 11px;
                padding: 3px 8px;
                border-radius: 6px;
                pointer-events: none;
                z-index: 99999;
                white-space: nowrap;
            }
        `;
        doc.head.appendChild(style);

        // Create tooltip element
        const tooltip = doc.createElement('div');
        tooltip.className = 'pk-tooltip';
        tooltip.style.display = 'none';
        doc.body.appendChild(tooltip);

        let lastHovered = null;

        // Hover effect
        doc.addEventListener('mouseover', (e) => {
            const el = e.target;
            if (el === doc.body || el === doc.documentElement) return;

            if (lastHovered && lastHovered !== el) {
                lastHovered.removeAttribute('data-pk-hover');
            }

            if (!el.hasAttribute('data-pk-selected')) {
                el.setAttribute('data-pk-hover', '');
            }

            lastHovered = el;

            // Show tooltip
            const tag = el.tagName.toLowerCase();
            const id = el.id ? '#' + el.id : '';
            const cls = el.className && typeof el.className === 'string'
                ? '.' + el.className.split(' ').filter(c => c).slice(0, 2).join('.')
                : '';
            tooltip.textContent = tag + id + cls;
            tooltip.style.display = 'block';

            const rect = el.getBoundingClientRect();
            tooltip.style.left = Math.min(rect.left, doc.documentElement.clientWidth - 200) + 'px';
            tooltip.style.top = Math.max(0, rect.top - 24) + 'px';
        });

        doc.addEventListener('mouseout', (e) => {
            if (lastHovered) {
                lastHovered.removeAttribute('data-pk-hover');
                lastHovered = null;
            }
            tooltip.style.display = 'none';
        });

        // Click to select element
        doc.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const el = e.target;

            // Clear previous selection
            doc.querySelectorAll('[data-pk-selected]').forEach(n => n.removeAttribute('data-pk-selected'));
            el.setAttribute('data-pk-selected', '');

            // Build selector
            let selector = el.tagName.toLowerCase();
            if (el.id) selector = '#' + el.id;
            else if (el.className && typeof el.className === 'string') {
                const cls = el.className.split(' ').filter(c => c && !c.startsWith('pk-'))[0];
                if (cls) selector = el.tagName.toLowerCase() + '.' + cls;
            }

            // Get content
            const content = el.tagName === 'IMG' ? el.src : el.textContent.trim().substring(0, 500);

            // Dispatch to Livewire
            this.$wire.onIframeElementClicked(selector, content, el.tagName.toLowerCase());
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
                el.setAttribute('data-pk-selected', '');
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } catch (e) {
            // Invalid selector, ignore
        }
    },

    reloadIframe() {
        this.iframeLoading = true;
        const iframe = this.$refs.previewFrame;
        if (iframe) iframe.src = iframe.src;
    },
}));
</script>
@endscript
