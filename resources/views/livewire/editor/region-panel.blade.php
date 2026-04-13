<div class="flex h-full flex-col">
    <div class="border-b border-zinc-800 px-3 py-3">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-300">Layers</p>
        <p class="mt-1 text-[11px] text-zinc-500">
            @if ($editorProfile['visual_editing_supported'])
                {{ $visualEditableCount }} editable layer{{ $visualEditableCount === 1 ? '' : 's' }}. Click a row to focus it in canvas.
            @else
                Code-first page. Layers help you locate elements before editing source.
            @endif
        </p>
    </div>

    <div class="border-b border-zinc-800 px-3 py-2">
        <div class="grid grid-cols-4 gap-1">
            @foreach (['all', 'dynamic', 'static', 'unconfirmed'] as $tab)
                <button
                    type="button"
                    wire:click="$set('filter', '{{ $tab }}')"
                    @class([
                        'rounded-md px-2 py-1 text-[11px] font-medium transition',
                        'bg-violet-500/20 text-violet-200 border border-violet-500/30' => $filter === $tab,
                        'border border-zinc-700 text-zinc-400 hover:border-zinc-600 hover:text-zinc-200' => $filter !== $tab,
                    ])
                >
                    {{ ucfirst($tab) }}
                    <span class="ml-1 font-mono text-[10px] opacity-70">{{ $counts[$tab] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="flex-1 overflow-y-auto bg-zinc-950/40">
        <div class="border-b border-zinc-800/80 bg-zinc-950/60 px-3 py-2 text-[10px] text-zinc-500">
            Layers are grouped by the top-level selector (first segment). Nesting matches the canvas tree.
        </div>
        @forelse ($layerGroups as $group)
            <div wire:key="layer-group-{{ $loop->index }}" class="border-b border-zinc-800/80">
                <div
                    class="sticky top-0 z-10 flex items-center gap-2 border-b border-zinc-800/60 bg-zinc-900/95 px-3 py-2 backdrop-blur-sm"
                    role="presentation"
                >
                    <span
                        class="shrink-0 rounded border border-cyan-500/40 bg-cyan-500/10 px-1.5 py-0.5 font-mono text-[10px] text-cyan-200"
                    >{{ $group['token'] }}</span>
                    <span class="min-w-0 flex-1 truncate text-[11px] font-semibold uppercase tracking-wide text-zinc-400">
                        {{ $group['label'] }}
                    </span>
                    <span class="shrink-0 font-mono text-[10px] text-zinc-500">{{ count($group['regions']) }}</span>
                </div>

                @foreach ($group['regions'] as $region)
                    @php
                        $path = trim((string) ($region->selector ?? ''));
                        $segments = $path !== '' ? preg_split('/\s*>\s*/', $path) : [];
                        $lastSegment = $segments && count($segments) ? $segments[count($segments) - 1] : '';
                        $normalizedSegment = preg_replace('/\s+/', '', strtolower((string) $lastSegment));
                        $tagName = preg_replace('/[^a-z0-9_-].*/', '', (string) $normalizedSegment) ?: 'element';
                        $tagToken = '<' . $tagName . '>';
                        $level = max(0, count($segments ?? []) - 1);
                        $depth = min($level, 8);
                        $label = Str::limit(strip_tags($region->current_content ?? ''), 46) ?: '(empty)';
                        $isVisual = (bool) ($visualEditability[$region->id] ?? false);
                        $isManaged = $region->isConfirmed() || $region->hasVerifiedAnchor();
                    @endphp
                    <button
                        type="button"
                        wire:key="layer-{{ $region->id }}"
                        wire:click="selectRegion('{{ $region->id }}')"
                        data-layer-row
                        data-layer-region-id="{{ $region->id }}"
                        data-layer-selector="{{ $region->selector }}"
                        @class([
                            'group relative block w-full border-b border-zinc-800/50 py-2 pr-2 text-left transition last:border-b-0',
                            'bg-violet-500/15 ring-1 ring-inset ring-violet-500/55' => $selectedRegionId === $region->id,
                            'hover:bg-zinc-800/55' => $selectedRegionId !== $region->id,
                        ])
                        style="padding-left: {{ 12 + ($depth * 14) }}px;"
                        title="{{ $region->selector }}"
                    >
                        @if ($depth > 0)
                            @for ($i = 1; $i <= $depth; $i++)
                                <span
                                    class="pointer-events-none absolute top-0 bottom-0 w-px bg-zinc-700/70"
                                    style="left: {{ 10 + (($i - 1) * 14) }}px;"
                                    aria-hidden="true"
                                ></span>
                            @endfor
                            <span
                                class="pointer-events-none absolute h-px bg-zinc-600/80"
                                style="left: {{ 10 + (($depth - 1) * 14) }}px; width: 10px; top: 19px;"
                                aria-hidden="true"
                            ></span>
                        @endif

                        <div class="flex items-center gap-2">
                            <span
                                class="shrink-0 rounded border border-fuchsia-500/45 bg-fuchsia-500/10 px-1.5 py-0.5 font-mono text-[10px] text-fuchsia-200"
                            >{{ $tagToken }}</span>
                            <p class="min-w-0 flex-1 truncate text-[12px] font-medium leading-5 text-zinc-100">
                                {{ $label }}
                            </p>
                            <span
                                @class([
                                    'shrink-0 rounded border px-1.5 py-0.5 text-[10px] uppercase tracking-wide',
                                    'border-violet-500/40 bg-violet-500/10 text-violet-200' => $isVisual,
                                    'border-amber-500/40 bg-amber-500/10 text-amber-200' => ! $isVisual,
                                ])
                            >
                                {{ $isVisual ? 'visual' : 'code' }}
                            </span>
                        </div>

                        <div class="mt-1 pl-0.5">
                            <p class="truncate font-mono text-[10px] text-zinc-500">{{ $region->selector }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <span @class([
                                    'rounded border px-1.5 py-0.5 text-[10px]',
                                    'border-emerald-500/30 bg-emerald-500/10 text-emerald-200' => $isManaged,
                                    'border-zinc-700 bg-zinc-900 text-zinc-400' => ! $isManaged,
                                ])>{{ $isManaged ? 'managed' : 'auto' }}</span>
                                @if ($region->is_static)
                                    <span class="rounded border border-zinc-700 bg-zinc-900 px-1.5 py-0.5 text-[10px] text-zinc-400">locked</span>
                                @endif
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        @empty
            <div class="px-3 py-8 text-center text-sm text-zinc-500">
                No layers yet. Parse this page first.
            </div>
        @endforelse
    </div>
</div>
