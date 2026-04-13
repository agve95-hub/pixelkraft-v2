{{--
    Pixelkraft dashboard mockup → existing Livewire + Alpine iframe editor.
    Layout and chrome follow https://dashboard.pixelkraft.pro/ ; persistence still flows
    through VisualEditor (Git save, regions, preview iframe) — we did not add a parallel SPA.
--}}
<div
    class="flex h-screen min-h-0 flex-col bg-zinc-950 text-zinc-100"
    x-data="editorState({
        previewRegions: @js($previewRegions),
        selectedRegionId: @js($selectedRegion?->id),
    })"
    x-on:highlight-region.window="highlightRegion($event.detail.selector, $event.detail.regionId, $event.detail.content)"
    x-on:reload-iframe.window="reloadIframe()"
    x-on:mouseover.capture="onLayerRowHover($event)"
    x-on:mouseout.capture="onLayerRowOut($event)"
    x-on:click.capture="onLayerRowClick($event)"
>
    {{-- Row 1: developer / git signals from mockup (mapped to edit session + admin flag) --}}
    <div class="flex flex-wrap items-center gap-2 border-b border-zinc-800/80 bg-zinc-900 px-3 py-1.5 text-[11px] text-zinc-400">
        <span class="rounded border border-zinc-700 bg-zinc-950 px-2 py-0.5 text-zinc-300">
            {{ auth()->user()?->isAdmin() ? 'Developer' : 'Editor' }}
        </span>
        @if ($editSession)
            <span class="font-mono text-zinc-500">{{ $editSession->working_branch }}</span>
            @if ($editSession->status === 'conflicted')
                <span class="rounded border border-amber-500/35 bg-amber-500/10 px-2 py-0.5 text-amber-200">Merge conflict detected</span>
                <button type="button" wire:click="setMode('code')" class="text-violet-300 underline decoration-violet-500/40 hover:text-violet-200">View conflict</button>
                <button type="button" wire:click="startFreshSession" class="text-zinc-400 hover:text-zinc-200">Dismiss</button>
            @endif
        @endif
        <span class="ml-auto hidden items-center gap-2 sm:flex">
            <span class="flex size-6 items-center justify-center rounded-md bg-gradient-to-br from-emerald-400 to-cyan-500 text-[10px] font-bold text-black">P</span>
            <span class="text-zinc-500">pixelkraft</span>
        </span>
    </div>

    {{-- Row 2: primary toolbar (breadcrumbs, status, actions) --}}
    <header class="flex flex-wrap items-center gap-3 border-b border-zinc-800 bg-zinc-900/95 px-3 py-2.5 backdrop-blur-sm">
        <a href="{{ route('sites.show', $site) }}" class="inline-flex size-8 items-center justify-center rounded-lg border border-zinc-700 text-zinc-300 transition hover:border-zinc-500 hover:text-white" title="Back to site">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
        </a>

        <div class="min-w-0 flex-1">
            <nav class="truncate text-[11px] text-zinc-500">
                <span>Sites</span>
                <span class="mx-1 text-zinc-600">/</span>
                <a href="{{ route('sites.show', $site) }}" class="text-zinc-400 hover:text-zinc-200">{{ $site->name }}</a>
                <span class="mx-1 text-zinc-600">/</span>
                <span class="text-zinc-300">{{ $page->title ?? Str::afterLast($page->file_path, '/') }}</span>
            </nav>
            <div class="mt-0.5 flex flex-wrap items-center gap-2">
                <h1 class="truncate text-sm font-semibold text-white">{{ $page->title ?? Str::afterLast($page->file_path, '/') }}</h1>
                <span @class([
                    'rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide',
                    'border-emerald-500/35 bg-emerald-500/10 text-emerald-200' => $page->is_published,
                    'border-zinc-600 bg-zinc-800 text-zinc-300' => ! $page->is_published,
                ])>
                    {{ $page->is_published ? 'Published' : 'Draft' }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            <button type="button" wire:click="undo" class="rounded-md border border-zinc-700 bg-zinc-950 p-1.5 text-zinc-400 hover:text-zinc-100 disabled:opacity-40" title="Undo" @disabled(! $canUndo)>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 6 6v0"/></svg>
            </button>
            <button type="button" wire:click="redo" class="rounded-md border border-zinc-700 bg-zinc-950 p-1.5 text-zinc-400 hover:text-zinc-100 disabled:opacity-40" title="Redo" @disabled(! $canRedo)>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 9 6 6m0 0-6 6m6-6H9a6 6 0 0 0-6 6v0"/></svg>
            </button>
        </div>

        @if ($mode === 'visual')
            <div class="flex items-center rounded-lg border border-zinc-700 bg-zinc-950 p-0.5" role="group" aria-label="Viewport">
                <button type="button" x-on:click="setViewport('desktop')" :class="viewport === 'desktop' ? 'bg-zinc-800 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-200'" class="rounded-md px-2.5 py-1 text-[11px] font-medium">Desktop</button>
                <button type="button" x-on:click="setViewport('tablet')" :class="viewport === 'tablet' ? 'bg-zinc-800 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-200'" class="rounded-md px-2.5 py-1 text-[11px] font-medium">Tablet</button>
                <button type="button" x-on:click="setViewport('mobile')" :class="viewport === 'mobile' ? 'bg-zinc-800 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-200'" class="rounded-md px-2.5 py-1 text-[11px] font-medium">Mobile</button>
            </div>
        @endif

        <div class="hidden h-6 w-px bg-zinc-800 sm:block"></div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="setMode('code')" @class([
                'rounded-lg border px-3 py-1.5 text-[12px] font-medium transition',
                'border-violet-500/50 bg-violet-500/15 text-violet-100' => $mode === 'code',
                'border-zinc-700 bg-zinc-950 text-zinc-300 hover:border-zinc-500' => $mode !== 'code',
            ])>Code</button>
            <button type="button" wire:click="openScheduleModal" class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-[12px] font-medium text-zinc-200 hover:border-zinc-500">Schedule</button>
            <button
                type="button"
                wire:click="saveDraft"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-[12px] font-medium text-zinc-200 hover:border-zinc-500"
                wire:loading.attr="disabled"
                wire:target="saveDraft"
            >Save draft</button>
            <a href="{{ route('sites.show', $site) }}" target="_blank" rel="noopener" class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-[12px] font-medium text-zinc-200 hover:border-zinc-500">Preview</a>
            <button
                type="button"
                wire:click="publishPage"
                class="rounded-lg bg-gradient-to-r from-emerald-500 to-cyan-500 px-3 py-1.5 text-[12px] font-semibold text-black shadow-sm hover:opacity-95"
                wire:loading.attr="disabled"
                wire:target="publishPage"
            >Publish</button>
        </div>
    </header>

    @if (session()->has('success') || session()->has('error') || session()->has('info'))
        <div class="border-b border-zinc-800 bg-zinc-900/60 px-3 py-2">
            @if (session()->has('success'))
                <div class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200">{{ session('success') }}</div>
            @endif
            @if (session()->has('error'))
                <div @class(['rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs text-red-200', 'mt-2' => session()->has('success')])>{{ session('error') }}</div>
            @endif
            @if (session()->has('info'))
                <div @class(['rounded-md border border-sky-500/30 bg-sky-500/10 px-3 py-2 text-xs text-sky-100', 'mt-2' => session()->has('success') || session()->has('error')])>{{ session('info') }}</div>
            @endif
        </div>
    @endif

    @if ($editSession?->status === 'conflicted')
        <div class="border-b border-red-500/25 bg-red-500/5 px-3 py-3">
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-red-200">This edit session is conflicted.</p>
                    <p class="mt-1 text-xs text-red-100/80">Open Code mode to reconcile, or start a fresh session before more visual edits.</p>
                </div>
                <button wire:click="setMode('code')" type="button" class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-xs text-zinc-100 hover:border-zinc-500">Open Code</button>
                <button wire:click="startFreshSession" type="button" class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-violet-500">Fresh session</button>
            </div>
        </div>
    @endif

    <div class="flex min-h-0 flex-1 flex-col overflow-hidden lg:flex-row">
        @if ($mode === 'visual')
            {{-- Left rail: mockup tabs; region list stays Livewire for real layer sync --}}
            <aside
                class="flex h-auto max-h-[40vh] w-full shrink-0 flex-col border-b border-zinc-800 bg-zinc-900 lg:h-full lg:max-h-none lg:w-[17rem] lg:border-b-0 lg:border-r"
                x-data="{ leftTab: 'layers' }"
            >
                <div class="grid grid-cols-3 border-b border-zinc-800 bg-zinc-950/40 p-1">
                    <button type="button" x-on:click="leftTab = 'layers'" :class="leftTab === 'layers' ? 'bg-zinc-800 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-200'" class="rounded-md py-1.5 text-[11px] font-semibold">Layers</button>
                    <button type="button" x-on:click="leftTab = 'pages'" :class="leftTab === 'pages' ? 'bg-zinc-800 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-200'" class="rounded-md py-1.5 text-[11px] font-semibold">Pages</button>
                    <button type="button" x-on:click="leftTab = 'media'" :class="leftTab === 'media' ? 'bg-zinc-800 text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-200'" class="rounded-md py-1.5 text-[11px] font-semibold">Media</button>
                </div>

                <div class="min-h-0 flex-1 overflow-hidden">
                    <div x-show="leftTab === 'layers'" x-cloak class="h-full min-h-0">
                        @livewire('editor.region-panel', ['pageId' => $pageId, 'variant' => 'compact'], key('region-panel-'.$pageId))
                    </div>

                    <div x-show="leftTab === 'pages'" x-cloak class="h-full overflow-y-auto p-3 text-[12px]">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Pages</p>
                        <ul class="mt-2 space-y-1">
                            @foreach ($sitePages as $sitePage)
                                <li>
                                    <a
                                        href="{{ route('editor', ['site' => $site, 'page' => $sitePage]) }}"
                                        @class([
                                            'flex items-center justify-between gap-2 rounded-lg border px-2.5 py-2 transition',
                                            'border-violet-500/40 bg-violet-500/10 text-white' => (string) $sitePage->id === (string) $page->id,
                                            'border-zinc-800 bg-zinc-950/80 text-zinc-300 hover:border-zinc-600 hover:text-white' => (string) $sitePage->id !== (string) $page->id,
                                        ])
                                    >
                                        <span class="min-w-0 truncate font-medium">{{ $sitePage->title ?: ($sitePage->url_path ?: '/') }}</span>
                                        @if (! $sitePage->is_published)
                                            <span class="shrink-0 rounded border border-amber-500/35 bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-amber-200">Draft</span>
                                        @elseif ((string) $sitePage->id === (string) $page->id)
                                            <span class="shrink-0 rounded border border-emerald-500/35 bg-emerald-500/10 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-emerald-200">Live</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <button type="button" disabled class="mt-3 w-full rounded-lg border border-dashed border-zinc-700 py-2 text-[11px] text-zinc-500" title="Coming soon">+ New page</button>
                    </div>

                    <div x-show="leftTab === 'media'" x-cloak class="h-full overflow-y-auto p-3 text-[12px]">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Media</p>
                        @forelse ($mediaSamples as $asset)
                            <div class="mt-2 flex items-center gap-2 rounded-lg border border-zinc-800 bg-zinc-950/80 px-2 py-1.5">
                                <span class="text-base" aria-hidden="true">
                                    @if (Str::endsWith(strtolower($asset['label']), '.svg'))
                                        🔷
                                    @else
                                        🖼
                                    @endif
                                </span>
                                <span class="min-w-0 truncate font-mono text-[11px] text-zinc-300" title="{{ $asset['path'] }}">{{ $asset['label'] }}</span>
                            </div>
                        @empty
                            <p class="mt-3 text-[11px] leading-relaxed text-zinc-500">No images detected in common asset folders. Use Files for the full library.</p>
                        @endforelse
                        <a href="{{ route('sites.files', $site) }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-zinc-700 py-2 text-[11px] font-medium text-zinc-200 hover:border-zinc-500">Open Files</a>
                    </div>
                </div>
            </aside>

            {{-- Canvas column --}}
            <div class="relative flex min-h-0 min-w-0 flex-1 flex-col bg-zinc-950">
                <div class="flex flex-wrap items-center gap-2 border-b border-zinc-800/80 bg-zinc-900/40 px-3 py-2">
                    <button type="button" x-on:click="toggleBorders()" class="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1 text-[11px] text-zinc-300 hover:border-zinc-500">
                        <span x-text="bordersVisible ? 'Overlays on' : 'Overlays off'"></span>
                    </button>
                    <button type="button" wire:click="reparsePage" class="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1 text-[11px] text-zinc-300 hover:border-zinc-500" wire:loading.attr="disabled" wire:target="reparsePage">Re-parse</button>
                    <span class="ml-auto hidden font-mono text-[10px] text-zinc-600 sm:inline">{{ $codeFilePath }}</span>
                </div>

                <div class="relative min-h-0 flex-1 overflow-auto bg-[radial-gradient(circle_at_top,rgba(99,102,241,0.1),transparent_40%),linear-gradient(to_bottom,rgba(24,24,27,0.35),rgba(9,9,11,0.96))]">
                    <div class="flex min-h-full items-start justify-center px-3 py-6 sm:px-6">
                        <div
                            class="relative w-full overflow-hidden rounded-2xl border border-zinc-800 bg-white shadow-[0_30px_90px_rgba(0,0,0,0.45)] transition-all duration-200"
                            :style="viewportStyle()"
                        >
                            <iframe
                                x-ref="previewFrame"
                                src="{{ $previewUrl }}"
                                class="h-[min(78vh,820px)] w-full border-0 bg-white sm:h-[min(78vh,880px)]"
                                sandbox="allow-same-origin allow-scripts"
                                x-on:load="onIframeLoad()"
                                x-on:error="iframeLoading = false"
                            ></iframe>
                        </div>
                    </div>

                    {{-- Floating toolbar: actions map to existing Livewire region management + save modal --}}
                    @if ($selectedRegion)
                        <div class="pointer-events-auto absolute bottom-6 left-1/2 z-20 flex -translate-x-1/2 flex-wrap items-center gap-1 rounded-xl border border-zinc-700/90 bg-zinc-950/95 px-2 py-1.5 shadow-2xl backdrop-blur-md">
                            <span class="hidden max-w-[10rem] truncate px-2 text-[10px] font-mono text-zinc-500 sm:inline">{{ $selectedRegion->selector }}</span>
                            <button type="button" wire:click="promoteSelectedRegion" class="rounded-md px-2 py-1 text-[11px] text-zinc-200 hover:bg-zinc-800" title="Promote region">Promote</button>
                            <button type="button" wire:click="lockSelectedRegion" class="rounded-md px-2 py-1 text-[11px] text-zinc-200 hover:bg-zinc-800">Lock</button>
                            @if ($selectedRegionEditable)
                                <button type="button" wire:click="openSaveModal" class="rounded-md bg-violet-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-violet-500">Save</button>
                            @else
                                <button type="button" wire:click="setMode('code')" class="rounded-md px-2 py-1 text-[11px] text-amber-200 hover:bg-zinc-800">Code</button>
                            @endif
                        </div>
                    @endif

                    <div x-show="iframeLoading" class="absolute inset-0 flex items-center justify-center bg-zinc-950/80">
                        <div class="flex items-center gap-3 text-zinc-400">
                            <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Loading preview…
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right inspector --}}
            <aside class="flex max-h-[45vh] w-full shrink-0 flex-col border-t border-zinc-800 bg-zinc-900 lg:max-h-none lg:h-full lg:w-[22rem] lg:border-l lg:border-t-0 xl:w-[24rem]">
                <div class="border-b border-zinc-800 px-2 py-2">
                    <div class="grid grid-cols-4 gap-1">
                        <button type="button" x-on:click="rightPanelTab = 'props'" :class="rightPanelTab === 'props' ? 'border-violet-500/40 bg-violet-500/15 text-violet-100' : 'border-zinc-800 text-zinc-500 hover:text-zinc-200'" class="rounded-md border px-1.5 py-1.5 text-[10px] font-semibold uppercase tracking-wide">Properties</button>
                        <button type="button" x-on:click="rightPanelTab = 'seo'" :class="rightPanelTab === 'seo' ? 'border-violet-500/40 bg-violet-500/15 text-violet-100' : 'border-zinc-800 text-zinc-500 hover:text-zinc-200'" class="rounded-md border px-1.5 py-1.5 text-[10px] font-semibold uppercase tracking-wide">SEO</button>
                        <button type="button" x-on:click="rightPanelTab = 'history'" :class="rightPanelTab === 'history' ? 'border-violet-500/40 bg-violet-500/15 text-violet-100' : 'border-zinc-800 text-zinc-500 hover:text-zinc-200'" class="rounded-md border px-1.5 py-1.5 text-[10px] font-semibold uppercase tracking-wide">History</button>
                        <button type="button" x-on:click="rightPanelTab = 'code'" :class="rightPanelTab === 'code' ? 'border-violet-500/40 bg-violet-500/15 text-violet-100' : 'border-zinc-800 text-zinc-500 hover:text-zinc-200'" class="rounded-md border px-1.5 py-1.5 text-[10px] font-semibold uppercase tracking-wide">Code</button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-3 py-3 text-[13px] leading-relaxed">
                    <div x-show="rightPanelTab === 'props'" class="space-y-4">
                        @if (! $selectedRegion)
                            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-700 bg-zinc-950/50 px-4 py-10 text-center">
                                <p class="text-sm font-medium text-zinc-200">Select an element</p>
                                <p class="mt-1 text-[12px] text-zinc-500">Click a region in the canvas or pick a row in Layers to edit properties.</p>
                            </div>
                        @else
                            <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Layer</p>
                                <p class="mt-1 text-sm font-semibold text-white">{{ $selectedRegion->region_type }}</p>
                                <p class="mt-2 break-all font-mono text-[11px] text-zinc-400">{{ $selectedRegion->selector }}</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span @class([
                                        'rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase',
                                        'border-violet-500/35 bg-violet-500/10 text-violet-200' => $selectedRegionEditable,
                                        'border-amber-500/35 bg-amber-500/10 text-amber-200' => ! $selectedRegionEditable,
                                    ])>{{ $selectedRegionEditable ? 'Editable' : 'Preview only' }}</span>
                                    @if ($selectedRegionManagement['managed'])
                                        <span class="rounded-full border border-emerald-500/35 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold uppercase text-emerald-200">Managed</span>
                                    @endif
                                </div>
                                <div class="mt-4 flex flex-col gap-2">
                                    <button wire:click="promoteSelectedRegion" type="button" class="w-full rounded-lg bg-violet-600 py-2 text-xs font-semibold text-white hover:bg-violet-500" @disabled($selectedRegionManagement['locked'] ?? false)>{{ ($selectedRegionManagement['managed'] ?? false) ? 'Refresh anchor' : 'Promote to managed' }}</button>
                                    <button wire:click="lockSelectedRegion" type="button" class="w-full rounded-lg border border-zinc-700 py-2 text-xs font-medium text-zinc-200 hover:border-zinc-500">Mark static</button>
                                </div>
                            </div>
                        @endif

                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Page settings</p>
                            <p class="mt-2 text-[12px] text-zinc-400">Publish status is stored on the page record; Publish queues a deploy.</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="rounded-md border border-emerald-500/30 bg-emerald-500/10 px-2 py-1 text-[11px] text-emerald-200">Published</span>
                                <span class="rounded-md border border-zinc-700 px-2 py-1 text-[11px] text-zinc-400">Draft</span>
                                <span class="rounded-md border border-zinc-700 px-2 py-1 text-[11px] text-zinc-500">Scheduled (stub)</span>
                            </div>
                            <p class="mt-2 text-[11px] text-zinc-500">Slug: <span class="font-mono text-zinc-400">{{ $page->url_path ?: '/' }}</span></p>
                        </div>
                    </div>

                    <div x-show="rightPanelTab === 'seo'" x-cloak class="space-y-3">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-white">Page SEO</p>
                                <span class="rounded-full border border-zinc-700 px-2 py-0.5 font-mono text-[11px] text-zinc-300">{{ (int) $page->seo_score }}/100</span>
                            </div>
                            @php
                                $titleLen = Str::length((string) ($page->title ?? ''));
                                $metaLen = Str::length((string) ($page->meta_description ?? ''));
                            @endphp
                            <label class="mt-3 block text-[11px] font-medium text-zinc-400">Meta title <span class="font-mono text-zinc-500">{{ $titleLen }}/60</span></label>
                            <p class="mt-1 rounded-lg border border-zinc-800 bg-zinc-950 px-2 py-1.5 text-[12px] text-zinc-200">{{ $page->title ?: '—' }}</p>
                            <label class="mt-3 block text-[11px] font-medium text-zinc-400">Meta description</label>
                            <p class="mt-1 rounded-lg border border-zinc-800 bg-zinc-950 px-2 py-1.5 text-[12px] text-zinc-300">{{ $page->meta_description ?: 'Missing — add for better SEO' }}</p>
                            <label class="mt-3 block text-[11px] font-medium text-zinc-400">OG image URL</label>
                            <p class="mt-1 truncate rounded-lg border border-zinc-800 bg-zinc-950 px-2 py-1.5 font-mono text-[11px] text-zinc-400">{{ $page->og_image ?: '—' }}</p>
                            <label class="mt-3 block text-[11px] font-medium text-zinc-400">Canonical URL</label>
                            <p class="mt-1 truncate rounded-lg border border-zinc-800 bg-zinc-950 px-2 py-1.5 font-mono text-[11px] text-zinc-400">{{ $page->canonical_url ?: '—' }}</p>
                            <a href="{{ route('seo.meta', ['site' => $site, 'page' => $page]) }}" class="mt-3 inline-flex w-full items-center justify-center rounded-lg border border-zinc-700 py-2 text-xs font-medium text-zinc-100 hover:border-zinc-500">Open SEO editor</a>
                        </div>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Issues</p>
                            <ul class="mt-2 space-y-1.5 text-[12px] text-zinc-300">
                                @if (! $page->meta_description)
                                    <li class="flex gap-2"><span class="text-amber-400">•</span> Missing meta description</li>
                                @endif
                                <li class="flex gap-2"><span class="text-zinc-500">•</span> Structured data (JSON-LD) not analyzed in-editor</li>
                                @if ($page->canonical_url)
                                    <li class="flex gap-2"><span class="text-emerald-400">•</span> Canonical URL set</li>
                                @endif
                                @foreach ($seoIssues as $issue)
                                    <li class="flex gap-2">
                                        <span @class(['text-red-400' => $issue->severity === 'error', 'text-amber-400' => $issue->severity !== 'error'])>•</span>
                                        <span>{{ $issue->message }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <div x-show="rightPanelTab === 'history'" x-cloak class="space-y-3">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Revisions</p>
                            <div class="mt-2 flex items-center gap-2 text-[12px] text-emerald-200">
                                <span class="size-1.5 rounded-full bg-emerald-400"></span>
                                Current — {{ $page->is_published ? 'Live' : 'Draft workspace' }}
                            </div>
                            <ul class="mt-3 space-y-2">
                                @forelse ($recentRevisions as $revision)
                                    <li class="rounded-lg border border-zinc-800 bg-zinc-950 px-2.5 py-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-mono text-[10px] text-zinc-500">{{ Str::limit($revision->id, 7, '') }}</span>
                                            <span class="text-[11px] text-zinc-600" title="Revision restore is not wired yet">Restore</span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-zinc-500">{{ $revision->created_at?->diffForHumans() }} · {{ $revision->user?->name ?? 'Unknown' }}</p>
                                        <p class="mt-1 line-clamp-2 text-[12px] text-zinc-300">{{ Str::limit(strip_tags($revision->content_after ?? ''), 140) }}</p>
                                    </li>
                                @empty
                                    <li class="text-[12px] text-zinc-500">No revisions for the selected layer yet.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    <div x-show="rightPanelTab === 'code'" x-cloak class="space-y-3">
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Element HTML</p>
                            @if ($selectedRegion)
                                <pre class="mt-2 max-h-40 overflow-auto rounded-lg border border-zinc-800 bg-black/40 p-2 font-mono text-[10px] leading-relaxed text-emerald-200/90">{{ e(Str::limit($selectedRegion->current_content ?? '', 2000)) }}</pre>
                            @else
                                <p class="mt-2 text-[12px] text-zinc-500">Select an element to view its HTML snapshot from the database.</p>
                            @endif
                        </div>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Computed styles</p>
                            <p class="mt-2 text-[12px] text-zinc-500">Cross-origin iframe: computed styles are not surfaced here. Use browser devtools on preview.</p>
                        </div>
                        <div class="rounded-xl border border-zinc-800 bg-zinc-950/60 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">data-pk attributes</p>
                            <p class="mt-2 font-mono text-[11px] text-zinc-400">
                                @if ($selectedRegion)
                                    data-pk-region-id="{{ $selectedRegion->id }}"
                                @else
                                    —
                                @endif
                            </p>
                        </div>
                        <button wire:click="setMode('code')" type="button" class="w-full rounded-lg border border-zinc-700 py-2 text-xs font-medium text-zinc-100 hover:border-zinc-500">Open full Code mode</button>
                    </div>
                </div>
            </aside>
        @else
            <div class="flex min-h-0 min-w-0 flex-1 flex-col bg-zinc-950">
                <div class="flex flex-wrap items-center gap-2 border-b border-zinc-800 bg-zinc-900/40 px-3 py-2">
                    <button type="button" wire:click="setMode('visual')" class="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1 text-[11px] text-zinc-200 hover:border-zinc-500">← Visual</button>
                    <span class="rounded border border-zinc-700 bg-zinc-950 px-2 py-1 text-[11px] text-zinc-300">Code</span>
                    <span class="truncate font-mono text-[11px] text-zinc-500">{{ $codeFilePath }}</span>
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
        <details class="border-t border-zinc-800 bg-zinc-950/70 px-3 py-2 text-[11px] text-zinc-400">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" x-on:keydown.escape.window="$wire.set('showSaveModal', false)">
            <div class="w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl" x-on:click.outside="$wire.set('showSaveModal', false)">
                <h3 class="mb-1 text-sm font-semibold text-white">Save & push</h3>
                <p class="mb-4 text-[12px] text-zinc-500">Commits to GitHub from the current editor session.</p>
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-zinc-400">Commit message</label>
                        <input type="text" wire:model="commitMessage" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white placeholder:text-zinc-600 focus:border-violet-500 focus:outline-none" placeholder="Update content…" wire:keydown.enter="save">
                    </div>
                    <label class="flex items-start gap-3 rounded-lg border border-zinc-800 px-3 py-2.5 text-[13px] text-zinc-300">
                        <input type="checkbox" wire:model.live="deployAfterSave" class="mt-0.5 h-4 w-4 rounded border-zinc-700 bg-zinc-950 text-violet-500 focus:ring-violet-500">
                        <span>
                            Auto-deploy after push
                            <span class="block text-[11px] text-zinc-500">Matches mockup “Publish” speed; turn off for draft-only pushes.</span>
                        </span>
                    </label>
                    <div class="flex items-center gap-3">
                        <button wire:click="save" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">{{ $deployAfterSave ? 'Commit, push & deploy' : 'Commit & push' }}</span>
                            <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Saving…
                            </span>
                        </button>
                        <button wire:click="$set('showSaveModal', false)" type="button" class="rounded-lg px-3 py-2 text-sm text-zinc-400 hover:text-white">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showScheduleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 backdrop-blur-sm" x-on:keydown.escape.window="$wire.closeScheduleModal()">
            <div class="w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl" x-on:click.outside="$wire.closeScheduleModal()">
                <h3 class="text-sm font-semibold text-white">Schedule publish</h3>
                <p class="mt-1 text-[12px] leading-relaxed text-zinc-500">Page scheduling is not persisted yet; this modal mirrors the mockup while we keep deploys explicit.</p>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-zinc-400">Publish date &amp; time</label>
                        <input type="datetime-local" wire:model="schedulePublishAt" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-zinc-400">Branch</label>
                        <select wire:model="scheduleBranch" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-white focus:border-violet-500 focus:outline-none">
                            <option value="main">main</option>
                            <option value="staging">staging</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeScheduleModal" class="rounded-lg px-3 py-2 text-sm text-zinc-400 hover:text-white">Cancel</button>
                    <button type="button" wire:click="confirmSchedule" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Schedule</button>
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
    viewport: 'desktop',
    rightPanelTab: 'props',
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
    layerSyncQueued: false,

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

    onLayerRowHover(event) {
        const row = event.target?.closest?.('[data-layer-row]');
        if (!row) {
            return;
        }

        const regionId = row.getAttribute('data-layer-region-id');
        if (!regionId) {
            return;
        }

        const element = this.lastRegionLookupMap[regionId]
            || this.$refs.previewFrame?.contentDocument?.querySelector(`[data-pk-region-id="${regionId}"]`);
        if (!this.isElementNode(element)) {
            return;
        }

        if (this.hoveredRegionElement && this.hoveredRegionElement !== element) {
            this.hoveredRegionElement.removeAttribute('data-pk-hover');
        }

        this.hoveredRegionElement = element;
        if (!element.hasAttribute('data-pk-selected') && element !== this.inlineEditingElement) {
            element.setAttribute('data-pk-hover', '');
        }
        this.updateOverlayPosition(this.hoverOverlay, element);
        this.showTooltip(element);
    },

    onLayerRowOut(event) {
        const row = event.target?.closest?.('[data-layer-row]');
        if (!row) {
            return;
        }

        const related = event.relatedTarget;
        const relatedRow = related?.closest?.('[data-layer-row]');
        if (relatedRow === row) {
            return;
        }

        this.clearHoveredRegion();
    },

    onLayerRowClick(event) {
        const row = event.target?.closest?.('[data-layer-row]');
        if (!row) {
            return;
        }

        const regionId = row.getAttribute('data-layer-region-id');
        if (!regionId) {
            return;
        }

        this.queueLayerSync(regionId, false);
    },

    queueLayerSync(regionId = null, forceScroll = false) {
        if (regionId) {
            this.selectedRegionId = regionId;
        }

        if (this.layerSyncQueued) {
            return;
        }

        this.layerSyncQueued = true;
        requestAnimationFrame(() => {
            this.layerSyncQueued = false;
            this.syncLayerFocus(forceScroll);
        });
    },

    syncLayerFocus(forceScroll = false) {
        const allRows = Array.from(this.$el.querySelectorAll('[data-layer-row]'));
        if (allRows.length === 0) {
            return;
        }

        for (const row of allRows) {
            const isActive = row.getAttribute('data-layer-region-id') === this.selectedRegionId;
            row.classList.toggle('ring-1', isActive);
            row.classList.toggle('ring-violet-500/55', isActive);
            row.classList.toggle('bg-violet-500/15', isActive);
        }

        if (forceScroll) {
            this.scrollLayerRowIntoView(this.selectedRegionId);
        }
    },

    scrollLayerRowIntoView(regionId) {
        if (!regionId) {
            return;
        }

        const row = this.$el.querySelector(`[data-layer-row][data-layer-region-id="${regionId}"]`);
        if (!row) {
            return;
        }

        row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
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

    setViewport(viewport) {
        this.viewport = viewport;
    },

    viewportStyle() {
        if (this.viewport === 'tablet') {
            return 'max-width: 820px;';
        }

        if (this.viewport === 'mobile') {
            return 'max-width: 420px;';
        }

        return 'max-width: 100%;';
    },

    applyBorderVisibility(doc) {
        doc.documentElement.setAttribute('data-pk-borders', this.bordersVisible ? 'on' : 'off');
        doc.querySelectorAll('[data-pk-region]').forEach((element) => this.applyRegionOutlineStyles(element));
        this.refreshOverlayPositions();
    },

    isElementNode(element) {
        if (!element || element.nodeType !== 1) {
            return false;
        }

        const view = element.ownerDocument?.defaultView;
        if (view?.HTMLElement) {
            return element instanceof view.HTMLElement;
        }

        return typeof element.getAttribute === 'function';
    },

    applyRegionOutlineStyles(element) {
        if (!this.isElementNode(element)) {
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
        if (!this.isElementNode(element)) {
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
        if (!this.isElementNode(element) || !this.isRegionEditable(element) || !this.isInlineCapable(element)) {
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
        if (!this.isElementNode(element)) {
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
            if (!this.isElementNode(node)) {
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
            if (!this.isElementNode(node)) {
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
        if (!this.isElementNode(element)) {
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

    focusLayerRow(regionId, forceScroll = false) {
        if (!regionId) {
            return;
        }

        const row = this.$el.querySelector(`[data-layer-row][data-layer-region-id="${regionId}"]`);
        if (!row) {
            return;
        }

        if (forceScroll) {
            row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
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
        if (!doc || !this.isElementNode(element)) {
            return;
        }

        this.clearHoveredRegion();
        doc.querySelectorAll('[data-pk-selected]').forEach((node) => node.removeAttribute('data-pk-selected'));
        element.setAttribute('data-pk-selected', '');
        this.selectedRegionId = element.getAttribute('data-pk-region-id');
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
        this.updateOverlayPosition(this.selectedOverlay, element);
        this.queueLayerSync(this.selectedRegionId, false);

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
        if (!overlay || !this.isElementNode(element) || !this.bordersVisible) {
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
