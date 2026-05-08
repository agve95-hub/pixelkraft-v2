<x-layouts.app>
    <x-slot:title>Blog — {{ $site->name }}</x-slot:title>

    <div class="space-y-5">
        <div class="pk-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="pk-page-title">Blog</h1>
                <p class="pk-page-sub">Draft, schedule, and publish posts for {{ $site->name }}.</p>
            </div>
            <x-ui.button href="{{ route('blog.create', $site) }}" icon="plus" size="sm">New Post</x-ui.button>
        </div>

        <x-ui.table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th class="hidden md:table-cell">Slug</th>
                    <th>Status</th>
                    <th class="hidden lg:table-cell">Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($posts as $post)
                    <tr>
                        <td class="font-medium">{{ $post->title }}</td>
                        <td class="hidden md:table-cell font-mono text-xs">/{{ $post->slug }}</td>
                        <td>
                            @switch($post->status)
                                @case('published') <x-ui.badge variant="success">Published</x-ui.badge> @break
                                @case('scheduled') <x-ui.badge variant="warning">Scheduled</x-ui.badge> @break
                                @default <x-ui.badge>Draft</x-ui.badge>
                            @endswitch
                        </td>
                        <td class="hidden lg:table-cell text-xs">
                            {{ $post->published_at?->format('M j, Y') ?? $post->created_at->format('M j, Y') }}
                        </td>
                        <td>
                            <x-ui.button-group align="end">
                                <x-ui.button href="{{ route('blog.edit', [$site, $post]) }}" size="xs" variant="ghost">Edit</x-ui.button>
                            </x-ui.button-group>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-ui.empty icon="newspaper" title="No blog posts yet" description="Create the first post for {{ $site->name }}." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </div>
</x-layouts.app>

