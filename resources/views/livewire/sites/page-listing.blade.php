<div wire:poll.5s>
    <div class="flex items-center justify-between mb-4">
        <flux:heading size="sm">Pages</flux:heading>
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Search pages..." icon="magnifying-glass" size="sm" class="max-w-xs" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'title'" :direction="$sortDir" wire:click="sort('title')">Title</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'url_path'" :direction="$sortDir" wire:click="sort('url_path')">URL</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'seo_score'" :direction="$sortDir" wire:click="sort('seo_score')" class="hidden md:table-cell">SEO</flux:table.column>
            <flux:table.column class="hidden lg:table-cell">Visitors</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($pages as $page)
                <flux:table.row>
                    <flux:table.cell class="font-medium">
                        {{ $page->title ?? Str::afterLast($page->file_path, '/') }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="font-mono text-xs">{{ $page->url_path ?? $page->file_path }}</span>
                    </flux:table.cell>

                    <flux:table.cell class="hidden md:table-cell">
                        @php
                            $score = $page->seo_score;
                            $color = match(true) {
                                $score >= 80 => 'lime',
                                $score >= 50 => 'yellow',
                                default => 'red',
                            };
                        @endphp
                        <flux:badge size="sm" :color="$color" class="font-mono">{{ $score }}/100</flux:badge>
                    </flux:table.cell>

                    <flux:table.cell class="hidden lg:table-cell font-mono text-xs">
                        {{ number_format($page->totalVisitors()) }}
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($page->is_published)
                            <flux:badge size="sm" color="lime">Published</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">Draft</flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex items-center gap-1 justify-end">
                            <flux:button href="{{ route('seo.meta', ['site' => $page->site_id, 'page' => $page->id]) }}" size="xs" variant="ghost">SEO</flux:button>
                            <flux:button href="{{ route('editor', ['site' => $page->site_id, 'page' => $page->id]) }}" size="xs" variant="subtle">Edit</flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center py-12">
                        <flux:subheading>No pages discovered yet</flux:subheading>
                        <flux:text size="sm">Pages will appear after the site is synced and parsed.</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($pages->hasPages())
        <div class="mt-4">
            {{ $pages->links() }}
        </div>
    @endif
</div>
