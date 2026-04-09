<x-layouts.app>
    <x-slot:title>SEO - {{ $page->title ?? $page->file_path }}</x-slot:title>

    @php($editorSupport = app(\App\Services\SiteSupportService::class)->editorProfile($site, $page))
    @php($seoScore = $page->seo_score ?? 0)

    <div class="mx-auto max-w-3xl space-y-6" x-data="{ tab: 'meta' }">
        {{-- Header --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="inline-flex items-center gap-1 text-sm text-zinc-500 transition hover:text-violet-400">
                    &larr; {{ $site->name }}
                </a>
                <h1 class="mt-1 text-xl font-semibold text-zinc-900 dark:text-zinc-100">
                    SEO for {{ $page->title ?? $page->file_path }}
                </h1>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ $page->url_path }}</p>
            </div>

            <div @class([
                'flex items-center gap-1 rounded-full px-3 py-1 text-sm font-semibold tabular-nums',
                'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' => $seoScore >= 80,
                'bg-amber-500/10 text-amber-600 dark:text-amber-400' => $seoScore >= 50 && $seoScore < 80,
                'bg-red-500/10 text-red-600 dark:text-red-400' => $seoScore < 50,
            ])>
                <span @class([
                    'size-2 rounded-full',
                    'bg-emerald-500' => $seoScore >= 80,
                    'bg-amber-500' => $seoScore >= 50 && $seoScore < 80,
                    'bg-red-500' => $seoScore < 50,
                ])></span>
                {{ $seoScore }}/100
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-6 border-b border-zinc-200 dark:border-zinc-700">
            <button
                type="button"
                x-on:click="tab = 'meta'"
                x-bind:class="tab === 'meta' ? 'border-b-2 border-zinc-900 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="pb-3 text-sm font-medium transition"
            >Page SEO</button>
            <button
                type="button"
                x-on:click="tab = 'schema'"
                x-bind:class="tab === 'schema' ? 'border-b-2 border-zinc-900 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="pb-3 text-sm font-medium transition"
            >Structured data</button>
            <button
                type="button"
                x-on:click="tab = 'robots'"
                x-bind:class="tab === 'robots' ? 'border-b-2 border-zinc-900 dark:border-white text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="pb-3 text-sm font-medium transition"
            >Robots & indexing</button>
        </div>

        {{-- Source adapter notice --}}
        <div class="flex items-start gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/70 px-4 py-3.5 text-sm">
            <flux:icon name="information-circle" class="size-5 text-zinc-400 mt-0.5 shrink-0" />
            <div>
                <p class="font-medium text-zinc-900 dark:text-zinc-100 uppercase text-xs tracking-wider">
                    {{ $editorSupport['meta_editing_mode'] === 'unsupported' ? 'Code first' : 'Source adapter' }}
                </p>
                <p class="mt-0.5 text-zinc-600 dark:text-zinc-400">{{ $editorSupport['meta_notice'] }}</p>
            </div>
        </div>

        {{-- Tab content: Page SEO --}}
        <div x-show="tab === 'meta'" x-cloak>
            @livewire('seo.meta-editor', ['pageId' => $page->id], key('seo-meta-' . $page->id))
        </div>

        {{-- Tab content: Structured data --}}
        <div x-show="tab === 'schema'" x-cloak class="space-y-4">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-5 py-4">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Structured data for this page</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Use JSON-LD when you want search engines to understand this page as an article, FAQ, product, or business entity.
                </p>
            </div>
            @livewire('seo.schema-editor', ['pageId' => $page->id], key('seo-schema-' . $page->id))
        </div>

        {{-- Tab content: Robots & indexing --}}
        <div x-show="tab === 'robots'" x-cloak class="space-y-4">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-5 py-4">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Site-wide robots.txt</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    This file affects the whole site, not just <span class="font-medium text-zinc-900 dark:text-zinc-200">{{ $page->url_path }}</span>.
                </p>
            </div>
            @livewire('seo.robots-txt-editor', ['siteId' => $site->id], key('seo-robots-' . $site->id))
        </div>
    </div>
</x-layouts.app>
