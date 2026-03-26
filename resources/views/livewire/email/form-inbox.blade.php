<div class="space-y-4">
    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <select wire:model.live="siteId" class="flux-input text-sm w-auto">
            <option value="">All Sites</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}">{{ $site->name }}</option>
            @endforeach
        </select>

        <div class="flex gap-1">
            @foreach (['unread', 'all', 'spam'] as $tab)
                <button
                    wire:click="$set('filter', '{{ $tab }}')"
                    @class([
                        'px-3 py-1.5 rounded-lg text-xs font-medium transition',
                        'bg-violet-600/20 text-violet-400' => $filter === $tab,
                        'text-zinc-500 hover:text-zinc-300 hover:bg-zinc-800' => $filter !== $tab,
                    ])
                >
                    {{ ucfirst($tab) }}
                    <span class="ml-1 mono text-[10px] opacity-60">{{ $counts[$tab] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Submissions --}}
    <div class="space-y-2">
        @forelse ($submissions as $submission)
            <div @class([
                'rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 transition hover:bg-zinc-50 dark:hover:bg-white/5',
                'border-l-2 border-l-violet-500' => !$submission->is_read,
            ])>
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-medium text-zinc-200">{{ $submission->site?->name ?? 'Unknown site' }}</span>
                            <span class="flux-badge-purple !text-[10px]">{{ $submission->form_name }}</span>
                            @if (!$submission->is_read)
                                <span class="h-1.5 w-1.5 rounded-full bg-violet-400"></span>
                            @endif
                        </div>

                        {{-- Submission data --}}
                        <div class="space-y-1 mt-2">
                            @foreach ($submission->data as $key => $value)
                                @if (!str_starts_with($key, '_'))
                                    <div class="flex gap-2 text-xs">
                                        <span class="text-zinc-600 font-medium min-w-[80px]">{{ ucfirst($key) }}:</span>
                                        <span class="text-zinc-300 break-all">{{ is_string($value) ? Str::limit($value, 200) : json_encode($value) }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <p class="mono text-[10px] text-zinc-600 mt-2">
                            {{ $submission->created_at->diffForHumans() }}
                            · {{ $submission->ip_address }}
                        </p>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-1 flex-shrink-0">
                        @if (!$submission->is_read)
                            <button wire:click="markRead('{{ $submission->id }}')" class="flux-btn-ghost text-[10px] !px-2 !py-1" title="Mark read">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            </button>
                        @endif
                        <button wire:click="markSpam('{{ $submission->id }}')" class="flux-btn-ghost text-[10px] !px-2 !py-1 text-zinc-600 hover:text-amber-400" title="Mark spam">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126Z" /></svg>
                        </button>
                        <button wire:click="delete('{{ $submission->id }}')" wire:confirm="Delete this submission?" class="flux-btn-ghost text-[10px] !px-2 !py-1 text-zinc-600 hover:text-red-400" title="Delete">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="flux-card py-12 text-center text-sm text-zinc-500">
                @if ($filter === 'spam')
                    No spam submissions.
                @else
                    No form submissions yet. Add the form endpoint to your sites to start receiving submissions.
                @endif
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div>{{ $submissions->links() }}</div>

    {{-- Integration info --}}
    <div class="card">
        <h4 class="text-xs font-semibold text-zinc-200 mb-2">Integration</h4>
        <p class="text-xs text-zinc-500 mb-2">Point your contact forms to this endpoint:</p>
        <code class="block rounded bg-zinc-800 px-3 py-2 mono text-xs text-violet-400 break-all">
            POST {{ url('/api/forms') }}/{site-slug}
        </code>
        <p class="text-[10px] text-zinc-600 mt-2">Accepts any JSON body. Rate limited to 10 submissions/minute per IP.</p>
    </div>
</div>
