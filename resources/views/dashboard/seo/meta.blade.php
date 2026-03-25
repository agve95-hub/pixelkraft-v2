<x-layouts.app>
    <x-slot:title>SEO — {{ $page->title ?? $page->file_path }}</x-slot:title>

    <div class="max-w-3xl">
        <div class="mb-6">
            <a href="{{ route('sites.show', $site) }}" class="text-xs text-zinc-500 hover:text-violet-400 transition">← {{ $site->name }}</a>
            <h2 class="text-lg font-semibold text-zinc-100 mt-1">SEO: {{ $page->title ?? $page->url_path }}</h2>
            <p class="mono text-sm text-zinc-500">{{ $page->url_path }}</p>
        </div>

        @livewire('seo.meta-editor', ['pageId' => $page->id])

        <div class="mt-8">
            @livewire('seo.schema-editor', ['pageId' => $page->id])
        </div>

        <div class="mt-8">
            @livewire('seo.robots-txt-editor', ['siteId' => $site->id])
        </div>
    </div>
</x-layouts.app>
