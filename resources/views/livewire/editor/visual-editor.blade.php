<div
    class="flex h-[calc(100vh-3.5rem)] flex-col"
    x-data="editorState({
        previewRegions: @js($previewRegions),
        selectedRegionId: @js($selectedRegion?->id),
    })"
    x-on:highlight-region.window="highlightRegion($event.detail.selector, $event.detail.regionId, $event.detail.content)"
    x-on:reload-iframe.window="reloadIframe()"
>
    <div class="flex flex-wrap items-center gap-2 border-b border-zinc-800 bg-zinc-900/60 px-4 py-2">
        <a href="{{ route('sites.show', $site) }}" class="flux-btn-ghost text-xs !px-2 !py-1.5" title="Back to site">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
        </a>

        <div class="mr-auto min-w-0">
            <p class="truncate text-sm font-medium text-zinc-100">{{ $page->title ?? $page->file_path }}</p>
            <p class="truncate font-mono text-[11px] text-zinc-500">{{ $page->url_path }}</p>
        </div>

        <div class="flex items-center rounded-lg border border-zinc-700 bg-zinc-800 p-0.5">
            <button
                wire:click="setMode('visual')"
                @class([
                    'rounded-md px-3 py-1 text-xs font-medium transition',
                    'bg-violet-600 text-white' => $mode === 'visual',
                    'text-zinc-400 hover:text-zinc-200' => $mode !== 'visual',
                ])
            >
                Canvas
            </button>
            <button
                wire:click="setMode('code')"
                @class([
                    'rounded-md px-3 py-1 text-xs font-medium transition',
                    'bg-violet-600 text-white' => $mode === 'code',
                    'text-zinc-400 hover:text-zinc-200' => $mode !== 'code',
                ])
            >
                Code
            </button>
        </div>

        <button
            type="button"
            wire:click="reparsePage"
            class="flux-btn-ghost text-xs !px-2.5 !py-1.5"
            wire:loading.attr="disabled"
            wire:target="reparsePage"
            title="Refresh regions"
        >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
        </button>

        <button
            wire:click="save"
            class="flux-btn-primary text-xs !py-1.5"
            wire:loading.attr="disabled"
            wire:target="save"
            title="Save and push changes"
            @disabled($mode === 'visual' && ! $selectedRegionEditable)
        >
            <span wire:loading.remove wire:target="save">Save & Push</span>
            <span wire:loading wire:target="save" class="inline-flex items-center gap-1.5">
                <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                Saving...
            </span>
        </button>
    </div>

    @if (session()->has('success') || session()->has('error'))
        <div class="border-b border-zinc-800 bg-zinc-900/50 px-4 py-2">
            @if (session()->has('success'))
                <div class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-300">
                    {{ session('success') }}
                </div>
            @endif
            @if (session()->has('error'))
                <div class="mt-2 rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs text-red-300">
                    {{ session('error') }}
                </div>
            @endif
        </div>
    @endif

    <div class="flex min-h-0 flex-1 overflow-hidden">
        @if ($mode === 'visual')
            <aside
                x-show="showLayers"
                x-transition.opacity
                class="hidden h-full w-80 shrink-0 border-r border-zinc-800 bg-zinc-900 lg:flex lg:flex-col"
            >
                @livewire('editor.region-panel', ['pageId' => $pageId], key('region-panel'))
            </aside>

            <div class="flex min-w-0 flex-1 flex-col bg-zinc-950">
                <div class="flex flex-wrap items-center gap-2 border-b border-zinc-800 bg-zinc-900/40 px-4 py-2">
                    <button
                        type="button"
                        x-on:click="showLayers = !showLayers"
                        class="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1 text-[11px] text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100"
                    >
                        <span x-text="showLayers ? 'Hide Layers' : 'Show Layers'"></span>
                    </button>
                    <button
                        type="button"
                        x-on:click="toggleBorders()"
                        class="rounded-md border border-zinc-700 bg-zinc-900 px-2 py-1 text-[11px] text-zinc-300 transition hover:border-zinc-500 hover:text-zinc-100"
                    >
                        <span x-text="bordersVisible ? 'Borders: On' : 'Borders: Off'"></span>
                    </button>

                    <span class="rounded border border-zinc-700 bg-zinc-900 px-2 py-1 text-[11px] text-zinc-300">
                        {{ $patchableRegionCount }}/{{ $previewRegionCount }} visual-editable
                    </span>
                    <span class="truncate font-mono text-[11px] text-zinc-500">source: {{ $codeFilePath }}</span>

                    @if ($selectedRegion)
                        <span class="truncate rounded border border-zinc-700 bg-zinc-900 px-2 py-1 font-mono text-[11px] text-zinc-300">
                            {{ $selectedRegion->selector }}
                        </span>
                    @endif
                </div>

                <div class="relative min-h-0 flex-1">
                    <iframe
                        x-ref="previewFrame"
                        src="{{ $previewUrl }}"
                        class="h-full w-full border-0"
                        sandbox="allow-same-origin allow-scripts"
                        x-on:load="onIframeLoad()"
                        x-on:error="iframeLoading = false"
                    ></iframe>

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

                <div class="border-t border-zinc-800 bg-zinc-900/40 px-4 py-3">
                    @if (! $selectedRegion)
                        <p class="text-sm text-zinc-300">Hover to target, click inside the pink border, and type directly in the canvas.</p>
                        <p class="mt-1 text-xs text-zinc-500">No detached edit box: the edit happens inside the selected visual element.</p>
                    @elseif (! $selectedRegionEditable)
                        <p class="text-sm text-amber-200">This layer is preview-only and cannot be safely patched from canvas mode.</p>
                        <div class="mt-2 flex items-center gap-2">
                            <span class="rounded border border-zinc-700 bg-zinc-900 px-2 py-1 font-mono text-[11px] text-zinc-400">{{ $selectedRegion->selector }}</span>
                            <button wire:click="setMode('code')" class="flux-btn-secondary text-xs">Open in Code Mode</button>
                        </div>
                    @else
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2 text-[11px]">
                                <span class="rounded border border-violet-500/40 bg-violet-500/10 px-2 py-1 text-violet-200">editable</span>
                                <span class="rounded border border-zinc-700 bg-zinc-900 px-2 py-1 text-zinc-300">{{ $selectedRegion->region_type }}</span>
                                <span class="truncate rounded border border-zinc-700 bg-zinc-900 px-2 py-1 font-mono text-zinc-400">{{ $selectedRegion->selector }}</span>
                            </div>
                            <p class="text-xs text-zinc-400">Click the element once in canvas and type directly inside that pink border.</p>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="flex min-w-0 flex-1 flex-col bg-zinc-950">
                <div class="flex flex-wrap items-center gap-2 border-b border-zinc-800 bg-zinc-900/40 px-4 py-2">
                    <span class="rounded border border-zinc-700 bg-zinc-900 px-2 py-1 text-[11px] text-zinc-300">Code Layer</span>
                    <span class="truncate font-mono text-xs text-zinc-400">{{ $codeFilePath }}</span>
                    <span class="ml-auto rounded border border-zinc-700 bg-zinc-800 px-2 py-0.5 text-[10px] uppercase tracking-wide text-zinc-400">{{ $codeLanguage }}</span>
                </div>

                <div class="min-h-0 flex-1 overflow-hidden">
                    <textarea
                        wire:model.blur="codeContent"
                        class="h-full w-full resize-none border-0 bg-zinc-950 p-4 font-mono text-sm leading-6 text-zinc-200 focus:outline-none focus:ring-0"
                        spellcheck="false"
                        autocapitalize="off"
                        autocomplete="off"
                        autocorrect="off"
                        wrap="off"
                    ></textarea>
                </div>
            </div>
        @endif
    </div>

    @if ($debugTelemetryEnabled)
        <details class="border-t border-zinc-800 bg-zinc-950/70 px-4 py-2 text-[11px] text-zinc-400">
            <summary class="cursor-pointer select-none text-zinc-300">Debug telemetry</summary>
            <div class="mt-2 space-y-1 font-mono">
                <p>action: {{ $debugTelemetry['last_action'] ?? 'n/a' }}</p>
                <p>mode: {{ $debugTelemetry['mode'] ?? 'n/a' }}</p>
                <p>selected: {{ $debugTelemetry['selected_region_id'] ?? 'n/a' }}</p>
                <p>error: {{ $debugTelemetry['last_error'] ?? 'n/a' }}</p>
            </div>
        </details>
    @endif

    @if ($showSaveModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" x-on:keydown.escape.window="$wire.set('showSaveModal', false)">
            <div class="w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl" x-on:click.outside="$wire.set('showSaveModal', false)">
                <h3 class="mb-4 text-sm font-semibold text-zinc-200">Save & Push to GitHub</h3>

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

@script
<script>
Alpine.data('editorState', ({ previewRegions, selectedRegionId }) => ({
    iframeLoading: true,
    previewRegions,
    selectedRegionId,
    lastRegionLookupMap: {},
    hoveredRegionElement: null,
    inlineEditingElement: null,
    regionSyncTimer: null,
    hoverOverlay: null,
    selectedOverlay: null,
    tooltip: null,
    iframeBootstrapped: false,
    showLayers: true,
    bordersVisible: true,

    init() {
        const iframe = this.$refs.previewFrame;
        if (!iframe) {
            return;
        }

        iframe.addEventListener('load', () => this.onIframeLoad());

        queueMicrotask(() => this.bootstrapIframeIfReady());
        setTimeout(() => this.bootstrapIframeIfReady(), 80);
        setTimeout(() => this.bootstrapIframeIfReady(), 220);
    },

    bootstrapIframeIfReady() {
        const doc = this.$refs.previewFrame?.contentDocument;
        if (!doc) {
            return;
        }

        if (doc.readyState === 'interactive' || doc.readyState === 'complete') {
            this.onIframeLoad();
        }
    },

    onIframeLoad() {
        if (this.iframeBootstrapped) {
            return;
        }

        this.iframeLoading = false;
        this.injectOverlayScript();
        this.iframeBootstrapped = true;
    },

    injectOverlayScript() {
        const doc = this.$refs.previewFrame?.contentDocument;
        if (!doc) {
            return;
        }

        if (!doc.body) {
            setTimeout(() => this.injectOverlayScript(), 40);
            return;
        }

        if (!doc.documentElement.hasAttribute('data-pk-overlay-ready')) {
            doc.documentElement.setAttribute('data-pk-overlay-ready', 'true');

            const style = doc.createElement('style');
            style.textContent = `
                [data-pk-region] {
                    transition: outline-color 120ms ease, background-color 120ms ease, box-shadow 120ms ease;
                }
                html[data-pk-borders="on"] [data-pk-region] {
                    position: relative !important;
                    box-sizing: border-box !important;
                    border-radius: 4px !important;
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-editable="true"] {
                    outline: 1px solid rgba(244, 114, 182, 0.42) !important;
                    outline-offset: 1px !important;
                    box-shadow: inset 0 0 0 1px rgba(244, 114, 182, 0.2) !important;
                    background: rgba(244, 114, 182, 0.03) !important;
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-editable="false"] {
                    outline: 2px dashed rgba(245, 158, 11, 0.85) !important;
                    outline-offset: 2px !important;
                    box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.45) !important;
                    background: rgba(245, 158, 11, 0.05) !important;
                }
                html[data-pk-borders="on"] [data-pk-region]::before {
                    content: attr(data-pk-region-tag);
                    position: absolute;
                    top: 0;
                    left: 0;
                    transform: translateY(-100%);
                    padding: 2px 6px;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                    font-size: 10px;
                    text-transform: uppercase;
                    letter-spacing: 0.04em;
                    border-radius: 4px 4px 0 0;
                    white-space: nowrap;
                    z-index: 99997;
                    pointer-events: none;
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-editable="true"]::before {
                    color: #fdf2f8;
                    background: rgba(190, 24, 93, 0.95);
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-editable="false"]::before {
                    color: #ffedd5;
                    background: rgba(217, 119, 6, 0.95);
                }
                [data-pk-region][data-pk-editable="true"] {
                    cursor: text !important;
                }
                [data-pk-region][data-pk-editable="false"] {
                    cursor: not-allowed !important;
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-hover][data-pk-editable="true"] {
                    outline: 2px solid rgba(244, 114, 182, 0.98) !important;
                    outline-offset: 2px !important;
                    background: rgba(244, 114, 182, 0.12) !important;
                    box-shadow: inset 0 0 0 1px rgba(244, 114, 182, 0.4), 0 0 0 1px rgba(244, 114, 182, 0.45) !important;
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-hover][data-pk-editable="false"] {
                    outline: 3px solid rgba(245, 158, 11, 1) !important;
                    outline-offset: 2px !important;
                    background: rgba(245, 158, 11, 0.2) !important;
                    box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.55) !important;
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-selected][data-pk-editable="true"] {
                    outline: 3px solid rgba(236, 72, 153, 1) !important;
                    outline-offset: 2px !important;
                    background: rgba(236, 72, 153, 0.12) !important;
                    box-shadow: inset 0 0 0 1px rgba(236, 72, 153, 0.4), 0 0 0 2px rgba(236, 72, 153, 0.75) !important;
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-selected][data-pk-editable="true"]::before,
                html[data-pk-borders="on"] [data-pk-region][data-pk-editing][data-pk-editable="true"]::before {
                    content: "selected";
                    color: #fff1f2;
                    background: rgba(190, 24, 93, 0.98);
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-selected][data-pk-editable="true"]::before,
                html[data-pk-borders="on"] [data-pk-region][data-pk-editing][data-pk-editable="true"]::before {
                    content: "selected";
                    color: #fff1f2;
                    background: rgba(190, 24, 93, 0.98);
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-selected][data-pk-editable="false"] {
                    outline: 2px solid rgba(245, 158, 11, 0.95) !important;
                    outline-offset: 2px !important;
                    background: rgba(245, 158, 11, 0.12) !important;
                    box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.7) !important;
                }
                html[data-pk-borders="on"] [data-pk-region][data-pk-editing][data-pk-editable="true"] {
                    outline: 2px solid rgba(219, 39, 119, 1) !important;
                    outline-offset: 2px !important;
                    background: rgba(236, 72, 153, 0.18) !important;
                    box-shadow: inset 0 0 0 1px rgba(219, 39, 119, 0.55), 0 0 0 1px rgba(244, 114, 182, 0.9) !important;
                }
                .pk-overlay-box {
                    position: fixed;
                    pointer-events: none;
                    z-index: 99998;
                    border-radius: 4px;
                    display: none;
                }
                .pk-overlay-box--hover[data-pk-editable="true"] {
                    border: 2px solid rgba(244, 114, 182, 0.98);
                    box-shadow: 0 0 0 1px rgba(244, 114, 182, 0.65), inset 0 0 0 9999px rgba(244, 114, 182, 0.05);
                }
                .pk-overlay-box--hover[data-pk-editable="false"] {
                    border: 2px dashed rgba(245, 158, 11, 1);
                    box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.55), inset 0 0 0 9999px rgba(245, 158, 11, 0.1);
                }
                .pk-overlay-box--selected[data-pk-editable="true"] {
                    border: 3px solid rgba(236, 72, 153, 1);
                    box-shadow: 0 0 0 1px rgba(244, 114, 182, 0.9), inset 0 0 0 9999px rgba(236, 72, 153, 0.08);
                }
                .pk-overlay-box--selected[data-pk-editable="false"] {
                    border: 2px solid rgba(217, 119, 6, 1);
                    box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.75), inset 0 0 0 9999px rgba(245, 158, 11, 0.14);
                }
                .pk-tooltip {
                    position: fixed;
                    border: 1px solid #3f3f46;
                    background: #111827;
                    color: #e5e7eb;
                    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                    font-size: 11px;
                    padding: 5px 8px;
                    border-radius: 6px;
                    pointer-events: none;
                    z-index: 99999;
                    white-space: nowrap;
                    box-shadow: 0 12px 38px rgba(0, 0, 0, 0.4);
                }
            `;
            (doc.head || doc.documentElement).appendChild(style);

            this.tooltip = doc.createElement('div');
            this.tooltip.className = 'pk-tooltip';
            this.tooltip.style.display = 'none';
            doc.body.appendChild(this.tooltip);

            this.hoverOverlay = doc.createElement('div');
            this.hoverOverlay.className = 'pk-overlay-box pk-overlay-box--hover';
            doc.body.appendChild(this.hoverOverlay);

            this.selectedOverlay = doc.createElement('div');
            this.selectedOverlay.className = 'pk-overlay-box pk-overlay-box--selected';
            doc.body.appendChild(this.selectedOverlay);

            doc.addEventListener('click', (event) => {
                const regionElement = this.findRegionElement(event.target);
                const link = (event.target?.nodeType === 1 ? event.target : event.target?.parentElement)?.closest('a') ?? null;
                if (link) {
                    event.preventDefault();
                }
                if (!regionElement) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                this.selectRegionElement(regionElement, true);

                if (this.isRegionEditable(regionElement) && this.isInlineCapable(regionElement)) {
                    this.startInlineEditing(regionElement);
                }
            }, true);

            doc.addEventListener('mousemove', (event) => {
                const regionElement = this.findRegionElement(event.target);
                if (!regionElement) {
                    this.clearHoveredRegion();
                    return;
                }

                if (this.hoveredRegionElement && this.hoveredRegionElement !== regionElement) {
                    this.hoveredRegionElement.removeAttribute('data-pk-hover');
                }

                this.hoveredRegionElement = regionElement;
                if (!regionElement.hasAttribute('data-pk-selected') && regionElement !== this.inlineEditingElement) {
                    regionElement.setAttribute('data-pk-hover', '');
                }
                this.updateOverlayPosition(this.hoverOverlay, regionElement);
                this.showTooltip(regionElement);
            }, true);

            doc.addEventListener('keydown', (event) => {
                if (event.metaKey || event.ctrlKey || event.altKey) {
                    return;
                }

                const active = doc.activeElement;
                if (active && (active.matches('input,textarea,select') || active.isContentEditable)) {
                    return;
                }

                const canTriggerFromKey = event.key.length === 1 || event.key === 'Backspace' || event.key === 'Delete';
                if (!canTriggerFromKey) {
                    return;
                }

                const target = this.hoveredRegionElement
                    || (this.selectedRegionId ? this.lastRegionLookupMap[this.selectedRegionId] : null);
                if (!target || !this.isRegionEditable(target) || !this.isInlineCapable(target)) {
                    return;
                }

                this.selectRegionElement(target, true);
                this.startInlineEditing(target, event.key.length === 1 ? event.key : null);
                event.preventDefault();
            }, true);

            doc.addEventListener('mouseleave', () => this.clearHoveredRegion(), true);
            doc.addEventListener('scroll', () => this.refreshOverlayPositions(), true);
            doc.defaultView?.addEventListener('resize', () => this.refreshOverlayPositions());
        }

        this.applyBorderVisibility(doc);
        this.decoratePreviewRegions(doc);
        setTimeout(() => this.decoratePreviewRegions(doc), 50);
    },

    toggleBorders() {
        this.bordersVisible = !this.bordersVisible;
        const doc = this.$refs.previewFrame?.contentDocument;
        if (doc) {
            this.applyBorderVisibility(doc);
        }
    },

    applyBorderVisibility(doc) {
        doc.documentElement.setAttribute('data-pk-borders', this.bordersVisible ? 'on' : 'off');
        doc.querySelectorAll('[data-pk-region]').forEach((element) => this.applyRegionOutlineStyles(element));
        this.refreshOverlayPositions();
    },

    applyRegionOutlineStyles(element) {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        if (!this.bordersVisible) {
            element.style.removeProperty('border');
            element.style.removeProperty('border-radius');
            element.style.removeProperty('box-sizing');
            element.style.removeProperty('outline');
            element.style.removeProperty('outline-offset');
            element.style.removeProperty('box-shadow');
            element.style.removeProperty('background');
            return;
        }

        // Do not set border/background/box-shadow inline with !important — it overrides injected
        // hover/selected rules. Base + state styling lives in the iframe <style> block above.
        element.style.removeProperty('border');
        element.style.removeProperty('border-radius');
        element.style.removeProperty('box-sizing');
        element.style.removeProperty('outline');
        element.style.removeProperty('outline-offset');
        element.style.removeProperty('box-shadow');
        element.style.removeProperty('background');
    },

    isRegionEditable(element) {
        return element?.getAttribute('data-pk-editable') === 'true';
    },

    isInlineCapable(element) {
        if (!(element instanceof HTMLElement)) {
            return false;
        }

        const regionType = element.getAttribute('data-pk-region-type');
        if (regionType === 'image') {
            return false;
        }

        const blockedTags = ['IMG', 'INPUT', 'TEXTAREA', 'SELECT', 'VIDEO', 'SVG', 'PATH'];
        return !blockedTags.includes(element.tagName);
    },

    startInlineEditing(element, firstChar = null) {
        if (!(element instanceof HTMLElement) || !this.isRegionEditable(element) || !this.isInlineCapable(element)) {
            return;
        }

        if (this.inlineEditingElement && this.inlineEditingElement !== element) {
            this.finishInlineEditing(true);
        }

        if (this.inlineEditingElement === element) {
            if (firstChar) {
                this.insertTextAtCursor(element.ownerDocument, firstChar);
                this.queueRegionSync(element);
            }
            return;
        }

        this.inlineEditingElement = element;
        element.setAttribute('contenteditable', 'true');
        element.setAttribute('data-pk-editing', '');
        element.removeAttribute('data-pk-hover');
        element.focus({ preventScroll: true });

        const selection = element.ownerDocument.getSelection();
        const range = element.ownerDocument.createRange();
        range.selectNodeContents(element);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);

        if (firstChar) {
            this.insertTextAtCursor(element.ownerDocument, firstChar);
        }

        const onInput = () => this.queueRegionSync(element);
        const onBlur = () => this.finishInlineEditing(true);
        element.addEventListener('input', onInput);
        element.addEventListener('blur', onBlur, { once: true });
        element._pkInlineHandlers = { onInput };

        this.queueRegionSync(element);
    },

    insertTextAtCursor(doc, text) {
        const selection = doc.getSelection();
        if (!selection || selection.rangeCount === 0) {
            return;
        }

        const range = selection.getRangeAt(0);
        range.deleteContents();
        range.insertNode(doc.createTextNode(text));
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
    },

    finishInlineEditing(sync = false) {
        const element = this.inlineEditingElement;
        if (!element) {
            return;
        }

        const handlers = element._pkInlineHandlers;
        if (handlers?.onInput) {
            element.removeEventListener('input', handlers.onInput);
        }

        delete element._pkInlineHandlers;
        element.removeAttribute('contenteditable');
        element.removeAttribute('data-pk-editing');
        this.inlineEditingElement = null;

        if (sync) {
            this.syncRegionContent(element);
        }
    },

    queueRegionSync(element) {
        if (this.regionSyncTimer) {
            clearTimeout(this.regionSyncTimer);
        }

        this.regionSyncTimer = setTimeout(() => this.syncRegionContent(element), 120);
    },

    syncRegionContent(element) {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        const regionId = element.getAttribute('data-pk-region-id');
        if (!regionId) {
            return;
        }

        this.selectedRegionId = regionId;
        this.$wire.onRegionSelected(regionId);
        this.$wire.updateEditContent(this.extractEditableContent(element));
    },

    extractEditableContent(element) {
        const type = element.getAttribute('data-pk-region-type');
        if (type === 'image') {
            return element.getAttribute('src') || '';
        }

        if (type === 'link') {
            const text = (element.innerText || element.textContent || '').trim();
            return text || element.getAttribute('href') || '';
        }

        return (element.innerText || element.textContent || '').trim();
    },

    highlightRegion(selector, regionId = null, regionContent = '') {
        const doc = this.$refs.previewFrame?.contentDocument;
        if (!doc) {
            return;
        }

        const region = regionId ? this.previewRegions.find((item) => item.id === regionId) : null;
        doc.querySelectorAll('[data-pk-selected]').forEach((node) => node.removeAttribute('data-pk-selected'));

        const bySelector = this.findRegionBySelector(doc, selector);
        const byId = regionId ? doc.querySelector(`[data-pk-region-id="${regionId}"]`) : null;
        const byText = region
            ? this.findRegionFallbackElement(doc, region)
            : (regionContent ? this.findRegionByTextContent(doc, regionContent) : null);

        if (byText && region) {
            this.markRegionElement(byText, region);
        }

        const element = bySelector || byId || byText;
        if (element) {
            this.selectRegionElement(element, false);
        }
    },

    decoratePreviewRegions(doc) {
        this.lastRegionLookupMap = {};
        const seenRegionIds = new Set();

        this.previewRegions.forEach((region) => {
            if (seenRegionIds.has(region.id)) {
                return;
            }

            seenRegionIds.add(region.id);
            let matched = false;
            try {
                const elements = doc.querySelectorAll(region.selector);
                elements.forEach((element) => {
                    matched = this.markRegionElement(element, region) || matched;
                });
            } catch (error) {
                // Ignore invalid selectors and use fallback matching.
            }

            if (!matched) {
                const fallbackElement = this.findRegionFallbackElement(doc, region);
                if (fallbackElement) {
                    this.markRegionElement(fallbackElement, region);
                }
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

    findRegionBySelector(doc, selector) {
        try {
            return doc.querySelector(selector);
        } catch (error) {
            return null;
        }
    },

    findRegionByTextContent(doc, content) {
        const needle = this.normalizeText(content || '');
        if (!needle) {
            return null;
        }

        const candidates = this.collectCandidateElements(doc);
        let best = null;
        let bestScore = 0;
        for (const node of candidates) {
            if (!(node instanceof HTMLElement)) {
                continue;
            }

            const text = this.normalizeText(node.innerText || node.textContent || '');
            if (!text) {
                continue;
            }

            let score = 0;
            if (text === needle) {
                score = 3;
            } else if (text.includes(needle) || needle.includes(text)) {
                score = 2;
            } else if (this.textSimilarity(text, needle) >= 0.7) {
                score = 1;
            }

            if (score > bestScore) {
                best = node;
                bestScore = score;
            }
        }

        return best;
    },

    findRegionFallbackElement(doc, region) {
        const needle = this.normalizeText(region.raw_content || region.content || '');
        if (!needle) {
            return null;
        }

        const candidates = this.collectCandidateElements(doc);
        let best = null;
        let bestScore = 0;

        for (const node of candidates) {
            if (!(node instanceof HTMLElement)) {
                continue;
            }

            const text = this.normalizeText(node.innerText || node.textContent || '');
            if (!text) {
                continue;
            }

            let score = 0;
            if (text === needle) {
                score = 4;
            } else if (text.includes(needle)) {
                score = 3;
            } else if (needle.includes(text) && text.length >= 6) {
                score = 2;
            } else {
                const similarity = this.textSimilarity(text, needle);
                if (similarity >= 0.72) {
                    score = 1 + similarity;
                }
            }

            if (node.hasAttribute('data-pk-region-id') && node.getAttribute('data-pk-region-id') !== region.id) {
                score -= 1;
            }

            if (text.length > needle.length * 6) {
                score -= 0.3;
            }

            if (score > bestScore) {
                best = node;
                bestScore = score;
            }
        }

        return bestScore >= 1 ? best : null;
    },

    markRegionElement(element, region) {
        if (!(element instanceof HTMLElement)) {
            return false;
        }

        const existing = element.getAttribute('data-pk-region-id');
        if (existing && existing !== region.id) {
            return false;
        }

        element.setAttribute('data-pk-region', '');
        element.setAttribute('data-pk-region-id', region.id);
        element.setAttribute('data-pk-editable', region.editable ? 'true' : 'false');
        element.setAttribute('data-pk-region-type', region.type);
        element.setAttribute('data-pk-region-label', region.content || region.type);
        element.setAttribute('data-pk-region-role', region.editable ? 'editable' : 'code');
        const tagLabel = `<${String(element.tagName || '').toLowerCase()}>`;
        element.setAttribute('data-pk-region-tag', tagLabel);
        this.applyRegionOutlineStyles(element);
        this.lastRegionLookupMap[region.id] = element;

        return true;
    },

    collectCandidateElements(doc) {
        return Array.from(doc.querySelectorAll('h1,h2,h3,h4,h5,h6,p,li,a,button,label,blockquote,figcaption,span,div,section,article'));
    },

    normalizeText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    },

    textSimilarity(a, b) {
        const aTokens = new Set(this.normalizeText(a).split(/\s+/).filter(Boolean));
        const bTokens = new Set(this.normalizeText(b).split(/\s+/).filter(Boolean));
        if (!aTokens.size || !bTokens.size) {
            return 0;
        }

        let overlap = 0;
        for (const token of aTokens) {
            if (bTokens.has(token)) {
                overlap++;
            }
        }

        return overlap / Math.max(aTokens.size, bTokens.size);
    },

    clearHoveredRegion() {
        if (this.hoveredRegionElement) {
            this.hoveredRegionElement.removeAttribute('data-pk-hover');
            this.hoveredRegionElement = null;
        }

        if (this.hoverOverlay) {
            this.hoverOverlay.style.display = 'none';
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
        const mode = this.isRegionEditable(element) ? 'click + type' : 'code mode only';
        this.tooltip.textContent = `${label} • ${mode}`;
        this.tooltip.style.display = 'block';

        const rect = element.getBoundingClientRect();
        this.tooltip.style.left = `${Math.min(rect.left, element.ownerDocument.documentElement.clientWidth - 280)}px`;
        this.tooltip.style.top = `${Math.max(0, rect.top - 32)}px`;
    },

    selectRegionElement(element, notifyLivewire = true) {
        const doc = this.$refs.previewFrame?.contentDocument;
        if (!doc || !(element instanceof HTMLElement)) {
            return;
        }

        this.clearHoveredRegion();
        doc.querySelectorAll('[data-pk-selected]').forEach((node) => node.removeAttribute('data-pk-selected'));
        element.setAttribute('data-pk-selected', '');
        this.selectedRegionId = element.getAttribute('data-pk-region-id');
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        this.updateOverlayPosition(this.selectedOverlay, element);

        if (notifyLivewire && this.selectedRegionId) {
            this.$wire.onRegionSelected(this.selectedRegionId);
        }
    },

    applySelectedRegion() {
        if (!this.selectedRegionId) {
            return;
        }

        const doc = this.$refs.previewFrame?.contentDocument;
        if (!doc) {
            return;
        }

        const element = this.lastRegionLookupMap[this.selectedRegionId]
            || doc.querySelector(`[data-pk-region-id="${this.selectedRegionId}"]`);
        if (element) {
            this.selectRegionElement(element, false);
        }
    },

    refreshOverlayPositions() {
        if (!this.bordersVisible) {
            if (this.hoverOverlay) {
                this.hoverOverlay.style.display = 'none';
            }
            if (this.selectedOverlay) {
                this.selectedOverlay.style.display = 'none';
            }
            return;
        }

        if (this.hoveredRegionElement) {
            this.updateOverlayPosition(this.hoverOverlay, this.hoveredRegionElement);
        } else if (this.hoverOverlay) {
            this.hoverOverlay.style.display = 'none';
        }

        const doc = this.$refs.previewFrame?.contentDocument;
        if (!doc || !this.selectedRegionId) {
            if (this.selectedOverlay) {
                this.selectedOverlay.style.display = 'none';
            }
            return;
        }

        const selectedElement = this.lastRegionLookupMap[this.selectedRegionId]
            || doc.querySelector(`[data-pk-region-id="${this.selectedRegionId}"]`);

        if (selectedElement) {
            this.updateOverlayPosition(this.selectedOverlay, selectedElement);
        } else if (this.selectedOverlay) {
            this.selectedOverlay.style.display = 'none';
        }
    },

    updateOverlayPosition(overlay, element) {
        if (!overlay || !(element instanceof HTMLElement) || !this.bordersVisible) {
            if (overlay) {
                overlay.style.display = 'none';
            }
            return;
        }

        const rect = element.getBoundingClientRect();
        if (rect.width < 2 || rect.height < 2) {
            overlay.style.display = 'none';
            return;
        }

        overlay.dataset.pkEditable = this.isRegionEditable(element) ? 'true' : 'false';
        overlay.style.left = `${Math.max(0, rect.left - 2)}px`;
        overlay.style.top = `${Math.max(0, rect.top - 2)}px`;
        overlay.style.width = `${Math.max(0, rect.width + 4)}px`;
        overlay.style.height = `${Math.max(0, rect.height + 4)}px`;
        overlay.style.display = 'block';
    },

    reloadIframe() {
        this.finishInlineEditing(false);
        this.iframeLoading = true;
        this.iframeBootstrapped = false;
        const iframe = this.$refs.previewFrame;
        if (!iframe) {
            return;
        }

        const url = new URL(iframe.src, window.location.origin);
        url.searchParams.set('_pk_preview', Date.now().toString());
        iframe.src = url.toString();
    },
}));
</script>
@endscript
