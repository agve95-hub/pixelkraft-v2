<x-layouts.app>
    <x-slot:title>Blog Posts — {{ $site->name }}</x-slot:title>

    <div class="max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="xl">Blog Posts</flux:heading>
                <flux:subheading>{{ $site->name }}</flux:subheading>
            </div>
            <flux:button href="{{ route('blog.create', $site) }}" variant="primary" icon="plus" size="sm">New Post</flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Title</flux:table.column>
                <flux:table.column class="hidden md:table-cell">Slug</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column class="hidden lg:table-cell">Date</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($site->blogPosts()->latest()->get() as $post)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ $post->title }}</flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell font-mono text-xs">/{{ $post->slug }}</flux:table.cell>
                        <flux:table.cell>
                            @switch($post->status)
                                @case('published') <flux:badge size="sm" color="lime">Published</flux:badge> @break
                                @case('scheduled') <flux:badge size="sm" color="yellow">Scheduled</flux:badge> @break
                                @default <flux:badge size="sm" color="zinc">Draft</flux:badge>
                            @endswitch
                        </flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell text-xs">
                            {{ $post->published_at?->format('M j, Y') ?? $post->created_at->format('M j, Y') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button href="{{ route('blog.edit', [$site, $post]) }}" size="xs" variant="ghost">Edit</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-12">
                            <flux:subheading>No blog posts yet.</flux:subheading>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</x-layouts.app>
