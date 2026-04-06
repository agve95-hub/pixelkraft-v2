<div class="flex flex-col h-full">
    @if ($isPreviewOnly)
        <div class="mx-3 mt-3 rounded-lg border border-amber-500/20 bg-amber-500/10 px-3 py-2 text-xs text-amber-100">
            Region detection is preview-only for this component-based page. Use Code mode for edits.
        </div>
    @endif

    {{-- Filter tabs --}}
    <div class="flex gap-1 px-3 py-2 border-b border-zinc-800">
        @foreach (['all', 'dynamic', 'static', 'unconfirmed'] as $tab)
            <button
                wire:click="$set('filter', '{{ $tab }}')"
                @class([
                    'px-2.5 py-1 rounded text-xs font-medium transition',
                    'bg-violet-600/20 text-violet-400' => $filter === $tab,
                    'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800' => $filter !== $tab,
                ])
            >
                {{ ucfirst($tab) }}
                <span class="ml-1 mono text-[10px] opacity-60">{{ $counts[$tab] }}</span>
            </button>
        @endforeach
    </div>

    {{-- Region list --}}
    <div class="flex-1 overflow-y-auto">
        @forelse ($regions as $region)
            <div
                wire:click="selectRegion('{{ $region->id }}')"
                class="flex items-start gap-3 px-3 py-2.5 border-b border-zinc-800/50 cursor-pointer hover:bg-zinc-800/30 transition group"
            >
                {{-- Type icon --}}
                <div class="mt-0.5 flex-shrink-0">
                    @switch($region->region_type)
                        @case('text')
                            <span class="flex h-5 w-5 items-center justify-center rounded bg-blue-500/10 text-blue-400 text-[10px] font-bold">T</span>
                            @break
                        @case('image')
                            <span class="flex h-5 w-5 items-center justify-center rounded bg-emerald-500/10 text-emerald-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 0 0 1.5-1.5V5.25a1.5 1.5 0 0 0-1.5-1.5H3.75a1.5 1.5 0 0 0-1.5 1.5v14.25c0 .828.672 1.5 1.5 1.5Z" /></svg>
                            </span>
                            @break
                        @case('link')
                            <span class="flex h-5 w-5 items-center justify-center rounded bg-violet-500/10 text-violet-400">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.86-3.01a4.5 4.5 0 0 0-1.242-7.244l-4.5-4.5a4.5 4.5 0 0 0-6.364 6.364l1.757 1.757" /></svg>
                            </span>
                            @break
                        @default
                            <span class="flex h-5 w-5 items-center justify-center rounded bg-zinc-500/10 text-zinc-400 text-[10px] font-bold">§</span>
                    @endswitch
                </div>

                {{-- Content preview --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="mono text-[10px] text-zinc-600 truncate max-w-[120px]">{{ $region->selector }}</span>

                        @if ($region->is_static)
                            <span class="flux-badge bg-zinc-500/10 text-zinc-500 !text-[10px] !px-1.5 !py-0">static</span>
                        @else
                            <span class="flux-badge-green !text-[10px] !px-1.5 !py-0">editable</span>
                        @endif

                        @if ($region->detection_method === 'auto')
                            <span class="flux-badge-amber !text-[10px] !px-1.5 !py-0">auto</span>
                        @elseif ($region->detection_method === 'marker')
                            <span class="flux-badge-purple !text-[10px] !px-1.5 !py-0">marked</span>
                        @endif
                    </div>

                    <p class="text-xs text-zinc-400 mt-0.5 truncate">
                        {{ Str::limit(strip_tags($region->current_content ?? ''), 80) ?: '(empty)' }}
                    </p>

                    {{-- Confidence bar --}}
                    <div class="flex items-center gap-2 mt-1">
                        <div class="flex-1 h-1 bg-zinc-800 rounded-full overflow-hidden">
                            <div
                                @class([
                                    'h-full rounded-full',
                                    'bg-emerald-500' => $region->confidence_score >= 0.7,
                                    'bg-amber-500' => $region->confidence_score >= 0.4 && $region->confidence_score < 0.7,
                                    'bg-red-500' => $region->confidence_score < 0.4,
                                ])
                                style="width: {{ $region->confidence_score * 100 }}%"
                            ></div>
                        </div>
                        <span class="mono text-[10px] text-zinc-600">{{ round($region->confidence_score * 100) }}%</span>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex-shrink-0 flex gap-1 opacity-0 group-hover:opacity-100 transition">
                    @if (! $isPreviewOnly && $region->detection_method === 'auto')
                        <button
                            wire:click.stop="confirmEditable('{{ $region->id }}')"
                            class="p-1 rounded text-emerald-500 hover:bg-emerald-500/10 transition"
                            title="Confirm as editable"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </button>
                        <button
                            wire:click.stop="confirmStatic('{{ $region->id }}')"
                            class="p-1 rounded text-zinc-500 hover:bg-zinc-500/10 transition"
                            title="Confirm as static"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        </button>
                    @elseif (! $isPreviewOnly)
                        <button
                            wire:click.stop="toggleRegion('{{ $region->id }}')"
                            class="p-1 rounded text-zinc-500 hover:bg-zinc-500/10 transition"
                            title="Toggle static/editable"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="py-8 text-center text-sm text-zinc-500">
                No regions detected yet. Parse the site first.
            </div>
        @endforelse
    </div>
</div>
