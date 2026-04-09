<div class="flex h-full flex-col">
    <div class="border-b border-zinc-800 px-3 py-3">
        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-300">Layers</p>
        <p class="mt-1 text-[11px] text-zinc-500">
            @if ($editorProfile['visual_editing_supported'])
                {{ $visualEditableCount }} editable layer{{ $visualEditableCount === 1 ? '' : 's' }}. Click a layer to focus it in preview.
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
                        'rounded px-2 py-1 text-[11px] font-medium transition',
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

    <div class="flex-1 overflow-y-auto">
        <div class="border-b border-zinc-800/80 bg-zinc-950/60 px-3 py-2 text-[10px] text-zinc-500">
            Nested tree follows canvas structure top-to-bottom.
        </div>
        @forelse ($regions as $region)
            @php
                $path = trim((string) ($region->selector ?? ''));
                $segments = $path !== '' ? preg_split('/\s*>\s*/', $path) : [];
                $lastSegment = $segments && count($segments) ? $segments[count($segments) - 1] : '';
                $normalizedSegment = preg_replace('/\s+/', '', strtolower((string) $lastSegment));
                $tagName = preg_replace('/[^a-z0-9_-].*/', '', (string) $normalizedSegment) ?: 'element';
                $tagToken = '<' . $tagName . '>';
                $level = max(0, count($segments ?? []) - 1);
            @endphp
            <button
                type="button"
                wire:key="layer-{{ $region->id }}"
                wire:click="selectRegion('{{ $region->id }}')"
                @class([
                    'group w-full border-b border-zinc-800 px-3 py-2.5 text-left transition',
                    'bg-violet-500/10 ring-1 ring-inset ring-violet-500/40' => $selectedRegionId === $region->id,
                    'hover:bg-zinc-800/50' => $selectedRegionId !== $region->id,
                ])
                style="padding-left: {{ 12 + min($level, 5) * 10 }}px;"
            >
                <div
                    class="absolute left-0 top-0 bottom-0 w-px bg-fuchsia-500/35"
                    style="left: {{ 8 + min($level, 6) * 14 }}px;"
                    aria-hidden="true"
                ></div>
                <div class="flex items-center justify-between gap-2">
                    <p class="truncate text-xs font-medium text-zinc-200">
                        {{ Str::limit(strip_tags($region->current_content ?? ''), 42) ?: '(empty)' }}
                    </p>
                    <span
                        @class([
                            'rounded border px-1.5 py-0.5 text-[10px] uppercase tracking-wide',
                            'border-violet-500/40 bg-violet-500/10 text-violet-200' => ($visualEditability[$region->id] ?? false),
                            'border-amber-500/40 bg-amber-500/10 text-amber-200' => ! ($visualEditability[$region->id] ?? false),
                        ])
                    >
                        {{ ($visualEditability[$region->id] ?? false) ? 'visual' : 'code' }}
                    </span>
                </div>

                <div class="mt-1 flex items-center gap-2">
                    <span class="rounded border border-fuchsia-500/40 bg-fuchsia-500/10 px-1.5 py-0.5 font-mono text-[10px] text-fuchsia-200">{{ $tagToken }}</span>
                    <p class="truncate font-mono text-[10px] text-zinc-500">{{ $region->selector }}</p>
                </div>

                <div class="mt-2 flex items-center gap-1.5 text-[10px] text-zinc-400">
                    <span class="rounded border border-zinc-700 px-1.5 py-0.5">{{ $region->region_type }}</span>
                    <span class="rounded border border-zinc-700 px-1.5 py-0.5">{{ $region->is_static ? 'static' : 'dynamic' }}</span>
                    <span class="rounded border border-zinc-700 px-1.5 py-0.5">{{ $region->detection_method }}</span>
                </div>
            </button>
        @empty
            <div class="px-3 py-8 text-center text-sm text-zinc-500">
                No layers yet. Parse this page first.
            </div>
        @endforelse
    </div>
</div>
