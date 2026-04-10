<div>
    <div class="rounded-2xl border border-zinc-800/90 bg-zinc-900/85 p-5 h-full">
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
                    class="group flex items-start gap-3 rounded-xl border border-zinc-800/70 bg-zinc-950/60 px-3 py-2.5 transition hover:border-zinc-700 hover:bg-zinc-950"
                >
                    @if ($issue['severity'] === 'warning')
                        <span class="mt-0.5 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-amber-400/15 text-amber-300">Warning</span>
                    @else
                        <span class="mt-0.5 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-sky-400/15 text-sky-300">Info</span>
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
