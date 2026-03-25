<x-layouts.app>
    <x-slot:title>Blog Posts — {{ $site->name }}</x-slot:title>

    <div class="max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100">Blog Posts</h2>
                <p class="text-sm text-zinc-500">{{ $site->name }}</p>
            </div>
            <a href="{{ route('blog.create', $site) }}" class="btn-primary text-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Post
            </a>
        </div>

        <div class="card overflow-hidden !p-0">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-zinc-800">
                        <th class="table-header px-4 py-3">Title</th>
                        <th class="table-header px-4 py-3 hidden md:table-cell">Slug</th>
                        <th class="table-header px-4 py-3">Status</th>
                        <th class="table-header px-4 py-3 hidden lg:table-cell">Date</th>
                        <th class="table-header px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($site->blogPosts()->latest()->get() as $post)
                        <tr class="hover:bg-zinc-800/30 transition">
                            <td class="table-cell font-medium text-zinc-100">{{ $post->title }}</td>
                            <td class="table-cell hidden md:table-cell mono text-xs text-zinc-500">/{{ $post->slug }}</td>
                            <td class="table-cell">
                                @switch($post->status)
                                    @case('published') <span class="badge-green">Published</span> @break
                                    @case('scheduled') <span class="badge-amber">Scheduled</span> @break
                                    @default <span class="badge bg-zinc-500/10 text-zinc-500">Draft</span>
                                @endswitch
                            </td>
                            <td class="table-cell hidden lg:table-cell text-xs text-zinc-500">
                                {{ $post->published_at?->format('M j, Y') ?? $post->created_at->format('M j, Y') }}
                            </td>
                            <td class="table-cell text-right">
                                <a href="{{ route('blog.edit', [$site, $post]) }}" class="btn-ghost text-xs !px-2 !py-1">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center text-sm text-zinc-500">No blog posts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
