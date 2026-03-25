<div>
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-semibold text-zinc-200">Pages</h3>
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search pages..."
            class="input-field max-w-xs text-sm"
        >
    </div>

    <div class="card overflow-hidden !p-0">
        <table class="w-full">
            <thead>
                <tr class="border-b border-zinc-800">
                    <th class="table-header px-4 py-3 cursor-pointer" wire:click="sort('title')">
                        <span class="flex items-center gap-1">
                            Title
                            @if ($sortBy === 'title')
                                <svg class="h-3 w-3 {{ $sortDir === 'desc' ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                            @endif
                        </span>
                    </th>
                    <th class="table-header px-4 py-3 cursor-pointer" wire:click="sort('url_path')">
                        <span class="flex items-center gap-1">
                            URL
                            @if ($sortBy === 'url_path')
                                <svg class="h-3 w-3 {{ $sortDir === 'desc' ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                            @endif
                        </span>
                    </th>
                    <th class="table-header px-4 py-3 hidden md:table-cell cursor-pointer" wire:click="sort('seo_score')">
                        <span class="flex items-center gap-1">
                            SEO
                            @if ($sortBy === 'seo_score')
                                <svg class="h-3 w-3 {{ $sortDir === 'desc' ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" /></svg>
                            @endif
                        </span>
                    </th>
                    <th class="table-header px-4 py-3 hidden lg:table-cell">Visitors</th>
                    <th class="table-header px-4 py-3">Status</th>
                    <th class="table-header px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pages as $page)
                    <tr class="group hover:bg-zinc-800/30 transition">
                        <td class="table-cell font-medium text-zinc-100">
                            {{ $page->title ?? Str::afterLast($page->file_path, '/') }}
                        </td>
                        <td class="table-cell">
                            <span class="mono text-xs text-zinc-500">{{ $page->url_path ?? $page->file_path }}</span>
                        </td>
                        <td class="table-cell hidden md:table-cell">
                            @php
                                $score = $page->seo_score;
                                $scoreClass = match(true) {
                                    $score >= 80 => 'text-emerald-400',
                                    $score >= 50 => 'text-amber-400',
                                    default => 'text-red-400',
                                };
                            @endphp
                            <span class="mono text-xs font-semibold {{ $scoreClass }}">{{ $score }}/100</span>
                        </td>
                        <td class="table-cell hidden lg:table-cell mono text-xs text-zinc-500">
                            {{ number_format($page->totalVisitors()) }}
                        </td>
                        <td class="table-cell">
                            @if ($page->is_published)
                                <span class="badge-green">Published</span>
                            @else
                                <span class="badge bg-zinc-500/10 text-zinc-500">Draft</span>
                            @endif
                        </td>
                        <td class="table-cell text-right">
                            <a href="{{ route('editor', ['site' => $page->site_id, 'page' => $page->id]) }}" class="btn-ghost text-xs !px-2 !py-1">
                                Edit
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center">
                            <div class="text-zinc-500">
                                <p class="text-sm">No pages discovered yet</p>
                                <p class="text-xs mt-1">Pages will appear after the site is synced and parsed</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
