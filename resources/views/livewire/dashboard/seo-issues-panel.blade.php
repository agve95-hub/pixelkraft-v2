<div>
    <div class="h-full rounded-xl border border-zinc-800/80 bg-[#1e1e1e] p-5">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:icon name="magnifying-glass" class="size-4 text-zinc-500" />
                <h3 class="text-sm font-semibold text-zinc-100">SEO</h3>
            </div>
            <span class="text-xs text-zinc-500">{{ $totalCount }} issues</span>
        </div>

        <div class="space-y-1.5">
            @forelse ($issues as $issue)
                <a
                    href="{{ route('seo.meta', [$issue['page']->site_id, $issue['page']->id]) }}"
                    class="group flex items-start gap-3 rounded-lg border border-zinc-700/60 bg-[#141414] px-3 py-2.5 transition hover:border-zinc-600 hover:bg-zinc-950/80"
                >
                    @if ($issue['severity'] === 'error')
                        <span class="mt-0.5 inline-flex rounded border border-red-600/60 bg-red-950/40 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-200">Error</span>
                    @elseif ($issue['severity'] === 'warning')
                        <span class="mt-0.5 inline-flex rounded border border-zinc-600 bg-zinc-950 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-100">Warning</span>
                    @else
                        <span class="mt-0.5 inline-flex rounded border border-zinc-600 bg-zinc-950 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-zinc-300">Info</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-zinc-100 transition group-hover:text-emerald-300">{{ $issue['message'] }}</p>
                        <p class="text-xs text-zinc-500">{{ $issue['site'] }}</p>
                    </div>
                </a>
            @empty
                <div class="py-6 text-center">
                    <flux:icon name="check-circle" variant="outline" class="mx-auto mb-2 size-6 text-emerald-400" />
                    <p class="text-sm text-zinc-400">No SEO issues found</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
