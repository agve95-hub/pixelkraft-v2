<div>
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-5 h-full">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <flux:icon name="magnifying-glass" class="size-4 text-zinc-400" />
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">SEO</h3>
            </div>
            <span class="text-xs text-zinc-400">{{ $totalCount }} issues</span>
        </div>

        <div class="space-y-1">
            @forelse ($issues as $issue)
                <a
                    href="{{ route('seo.meta', [$issue['page']->site_id, $issue['page']->id]) }}"
                    class="flex items-start gap-3 rounded-lg px-3 py-2 hover:bg-zinc-50 dark:hover:bg-white/5 transition group"
                >
                    @if ($issue['severity'] === 'warning')
                        <span class="mt-0.5 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400">Warning</span>
                    @else
                        <span class="mt-0.5 inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase bg-blue-500/10 text-blue-600 dark:text-blue-400">Info</span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-zinc-900 dark:text-zinc-100 group-hover:text-violet-500 dark:group-hover:text-violet-400 transition">{{ $issue['message'] }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $issue['site'] }}</p>
                    </div>
                </a>
            @empty
                <div class="py-6 text-center">
                    <flux:icon name="check-circle" variant="outline" class="size-6 text-lime-500 mx-auto mb-2" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No SEO issues found</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
