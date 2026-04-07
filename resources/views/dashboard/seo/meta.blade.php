<x-layouts.app>
    <x-slot:title>SEO - {{ $page->title ?? $page->file_path }}</x-slot:title>

    <div class="mx-auto max-w-6xl space-y-6" x-data="{ tab: 'meta' }">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="text-sm text-zinc-500 transition hover:text-violet-400">
                    <- {{ $site->name }}
                </a>
                <h1 class="mt-2 text-2xl font-semibold text-zinc-100">
                    SEO for {{ $page->title ?? $page->file_path }}
                </h1>
                <p class="mt-1 text-sm text-zinc-400">
                    {{ $page->url_path }}
                </p>
            </div>

            <div class="rounded-2xl border border-zinc-800 bg-zinc-900/70 p-1">
                <div class="flex flex-wrap gap-1">
                    <button
                        type="button"
                        x-on:click="tab = 'meta'"
                        x-bind:class="tab === 'meta' ? 'bg-violet-600 text-white' : 'text-zinc-400 hover:text-zinc-200'"
                        class="rounded-xl px-4 py-2 text-sm font-medium transition"
                    >
                        Page SEO
                    </button>
                    <button
                        type="button"
                        x-on:click="tab = 'schema'"
                        x-bind:class="tab === 'schema' ? 'bg-violet-600 text-white' : 'text-zinc-400 hover:text-zinc-200'"
                        class="rounded-xl px-4 py-2 text-sm font-medium transition"
                    >
                        Structured Data
                    </button>
                    <button
                        type="button"
                        x-on:click="tab = 'robots'"
                        x-bind:class="tab === 'robots' ? 'bg-violet-600 text-white' : 'text-zinc-400 hover:text-zinc-200'"
                        class="rounded-xl px-4 py-2 text-sm font-medium transition"
                    >
                        Site Robots
                    </button>
                </div>
            </div>
        </div>

        <div x-show="tab === 'meta'" x-cloak>
            @livewire('seo.meta-editor', ['pageId' => $page->id], key('seo-meta-' . $page->id))
        </div>

        <div x-show="tab === 'schema'" x-cloak class="space-y-4">
            <div class="card space-y-3">
                <h2 class="text-lg font-semibold text-zinc-100">Structured data for this page</h2>
                <p class="text-sm leading-6 text-zinc-400">
                    Use JSON-LD when you want search engines to understand this page as an article, FAQ, product, or business entity.
                    Start with a preset, then adjust only the fields you actually know.
                </p>
            </div>

            @livewire('seo.schema-editor', ['pageId' => $page->id], key('seo-schema-' . $page->id))
        </div>

        <div x-show="tab === 'robots'" x-cloak class="space-y-4">
            <div class="card space-y-3">
                <h2 class="text-lg font-semibold text-zinc-100">Site-wide robots.txt</h2>
                <p class="text-sm leading-6 text-zinc-400">
                    This file affects the whole site, not just <span class="font-medium text-zinc-200">{{ $page->url_path }}</span>.
                    Use it to allow crawling, block specific bots, or point crawlers to your sitemap.
                </p>
            </div>

            @livewire('seo.robots-txt-editor', ['siteId' => $site->id], key('seo-robots-' . $site->id))
        </div>
    </div>
</x-layouts.app>
