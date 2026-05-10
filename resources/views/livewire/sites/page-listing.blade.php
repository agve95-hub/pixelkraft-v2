<div wire:poll.5s>
    <div class="ui-list-toolbar">
        <p class="ui-list-toolbar-title">Pages</p>
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search pages..." icon="magnifying-glass" size="sm" class="ui-list-toolbar-control" />
    </div>

    <x-ui.table>
        <thead>
            <tr>
                <th><button wire:click="sort('title')" class="flex items-center gap-1 hover:text-zinc-200 transition-colors">Title @if ($sortBy === 'title') <flux:icon name="{{ $sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3" /> @endif</button></th>
                <th><button wire:click="sort('url_path')" class="flex items-center gap-1 hover:text-zinc-200 transition-colors">URL @if ($sortBy === 'url_path') <flux:icon name="{{ $sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3" /> @endif</button></th>
                <th class="hidden md:table-cell"><button wire:click="sort('seo_score')" class="flex items-center gap-1 hover:text-zinc-200 transition-colors">SEO @if ($sortBy === 'seo_score') <flux:icon name="{{ $sortDir === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="size-3" /> @endif</button></th>
                <th class="hidden lg:table-cell">Visitors</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pages as $page)
                @php
                    $score = $page->seo_score;
                    $scoreVariant = match(true) { $score >= 80 => 'success', $score >= 50 => 'warning', default => 'destructive' };
                @endphp
                <tr>
                    <td class="font-medium">{{ $page->title ?? Str::afterLast($page->file_path, '/') }}</td>
                    <td class="font-mono text-xs">{{ $page->url_path ?? $page->file_path }}</td>
                    <td class="hidden md:table-cell"><x-ui.badge variant="{{ $scoreVariant }}">{{ $score }}/100</x-ui.badge></td>
                    <td class="hidden lg:table-cell font-mono text-xs tabular-nums">{{ number_format($page->totalVisitors()) }}</td>
                    <td>
                        @if ($page->is_published)
                            <x-ui.badge variant="success">Published</x-ui.badge>
                        @else
                            <x-ui.badge>Draft</x-ui.badge>
                        @endif
                    </td>
                    <td>
                        <x-ui.button-group align="end">
                            <x-ui.button href="{{ route('seo.meta', ['site' => $page->site_id, 'page' => $page->id]) }}" size="xs" variant="ghost">SEO</x-ui.button>
                            <x-ui.button href="{{ route('editor', ['site' => $page->site_id, 'page' => $page->id]) }}" size="xs" variant="outline">Edit</x-ui.button>
                        </x-ui.button-group>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <x-ui.empty icon="document-duplicate" title="No pages discovered yet" description="Pages will appear after the site is synced and parsed." />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.table>

    @if ($pages->hasPages())
        <div class="mt-4">{{ $pages->links() }}</div>
    @endif
</div>
