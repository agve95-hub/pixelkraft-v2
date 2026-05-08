<x-layouts.app>
    <x-slot:title>SEO — {{ $page->title ?? $page->file_path }}</x-slot:title>

    @php
        $editorSupport = app(\App\Services\SiteSupportService::class)->editorProfile($site, $page);
        $seoScore = (int) ($page->seo_score ?? 0);
        $scoreVariant = match (true) { $seoScore >= 80 => 'success', $seoScore >= 50 => 'warning', default => 'destructive' };
        $pagePath = $page->url_path ?: '/';
        $siteBaseUrl = filled($site->domain) ? 'https://'.$site->domain : rtrim((string) config('app.url'), '/');
        $canonicalUrl = $page->canonical_url ?: rtrim($siteBaseUrl, '/').($pagePath === '/' ? '/' : $pagePath);
        $previewTitle = $page->title ?: \Illuminate\Support\Str::headline(\Illuminate\Support\Str::beforeLast(basename((string) $page->file_path), '.'));
        $previewDescription = $page->meta_description ?: 'Add a concise description so search results explain what this page is about.';
        $checks = [
            ['label' => 'Meta title', 'ok' => filled($page->title), 'note' => filled($page->title) ? mb_strlen((string) $page->title).' chars' : 'Missing title'],
            ['label' => 'Meta description', 'ok' => filled($page->meta_description), 'note' => filled($page->meta_description) ? mb_strlen((string) $page->meta_description).' chars' : 'Missing description'],
            ['label' => 'Canonical URL', 'ok' => filled($canonicalUrl), 'note' => $canonicalUrl],
            ['label' => 'Open Graph tags', 'ok' => filled($page->og_title) || filled($page->og_description) || filled($page->og_image), 'note' => filled($page->og_image) ? 'Social image set' : 'Uses meta fallback'],
            ['label' => 'Structured data', 'ok' => filled($page->schema_json), 'note' => filled($page->schema_json) ? 'JSON-LD present' : 'No structured data found'],
        ];
        $passedChecks = collect($checks)->where('ok', true)->count();
    @endphp

    <div class="space-y-5" x-data="{ tab: 'meta' }">
        <div class="pk-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link mb-2">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="pk-page-title">SEO Settings</h1>
                    <x-ui.badge variant="{{ $scoreVariant }}">{{ $seoScore }}/100</x-ui.badge>
                </div>
                <p class="pk-page-sub">{{ $site->name }} &middot; {{ $previewTitle }} <span class="rounded bg-zinc-800 px-1.5 py-0.5 font-mono text-[11px] text-zinc-400">{{ $pagePath }}</span></p>
            </div>
            <x-ui.button-group>
                <x-ui.button type="button" variant="outline" size="sm" x-on:click="tab = 'meta'">Run audit</x-ui.button>
                <x-ui.button href="{{ route('editor', ['site' => $site, 'page' => $page]) }}" size="sm" icon="pencil-square">Open editor</x-ui.button>
            </x-ui.button-group>
        </div>

        <div class="stats stats-4">
            <div class="stat">
                <p class="stat-label">SEO score</p>
                <p class="stat-val tabular-nums {{ $seoScore >= 80 ? 'text-emerald-400' : ($seoScore >= 50 ? 'text-amber-400' : 'text-red-400') }}">{{ $seoScore }}<span class="text-sm text-zinc-500">/100</span></p>
            </div>
            <div class="stat">
                <p class="stat-label">Checks passed</p>
                <p class="stat-val tabular-nums text-emerald-400">{{ $passedChecks }}<span class="text-sm text-zinc-500">/{{ count($checks) }}</span></p>
            </div>
            <div class="stat">
                <p class="stat-label">Issues</p>
                <p class="stat-val tabular-nums {{ count($checks) - $passedChecks > 0 ? 'text-amber-400' : 'text-emerald-400' }}">{{ count($checks) - $passedChecks }}</p>
            </div>
            <div class="stat">
                <p class="stat-label">Page status</p>
                <div class="mt-2">
                    <x-ui.badge variant="{{ $page->is_published ? 'success' : 'warning' }}" dot>{{ $page->is_published ? 'Published' : 'Draft' }}</x-ui.badge>
                </div>
            </div>
        </div>

        @if ($editorSupport['meta_editing_mode'] === 'unsupported')
            <x-ui.alert variant="warning" icon="information-circle" title="Code first">{{ $editorSupport['meta_notice'] }}</x-ui.alert>
        @endif

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)]">
            <section class="space-y-5">
                <x-ui.tabs>
                    <button type="button" x-on:click="tab = 'meta'" x-bind:class="{ 'is-active': tab === 'meta' }" class="pk-ui-tab">Meta tags</button>
                    <button type="button" x-on:click="tab = 'schema'" x-bind:class="{ 'is-active': tab === 'schema' }" class="pk-ui-tab">Structured data</button>
                    <button type="button" x-on:click="tab = 'robots'" x-bind:class="{ 'is-active': tab === 'robots' }" class="pk-ui-tab">Robots</button>
                </x-ui.tabs>

                <div x-show="tab === 'meta'" x-cloak>
                    <x-ui.card>
                        @livewire('seo.meta-editor', ['pageId' => $page->id], key('seo-meta-' . $page->id))
                    </x-ui.card>
                </div>

                <div x-show="tab === 'schema'" x-cloak>
                    <x-ui.card>
                        <x-ui.card-header>
                            <div>
                                <x-ui.card-title>Structured data (JSON-LD)</x-ui.card-title>
                                <x-ui.card-description>Describe this page as an article, FAQ, product, or local business entity.</x-ui.card-description>
                            </div>
                        </x-ui.card-header>
                        @livewire('seo.schema-editor', ['pageId' => $page->id], key('seo-schema-' . $page->id))
                    </x-ui.card>
                </div>

                <div x-show="tab === 'robots'" x-cloak>
                    <x-ui.card>
                        <x-ui.card-header>
                            <div>
                                <x-ui.card-title>Site-wide robots.txt</x-ui.card-title>
                                <x-ui.card-description>This file affects the whole site, not only <span class="font-mono text-zinc-300">{{ $pagePath }}</span>.</x-ui.card-description>
                            </div>
                        </x-ui.card-header>
                        @livewire('seo.robots-txt-editor', ['siteId' => $site->id], key('seo-robots-' . $site->id))
                    </x-ui.card>
                </div>
            </section>

            <aside class="space-y-5">
                <x-ui.card>
                    <x-ui.card-header>
                        <x-ui.card-title>Search result preview</x-ui.card-title>
                    </x-ui.card-header>
                    <div class="rounded-lg bg-white px-4 py-3 text-zinc-900">
                        <p class="font-mono text-[11px] text-zinc-700">{{ $canonicalUrl }}</p>
                        <p class="mt-1 text-lg leading-tight text-blue-700">{{ \Illuminate\Support\Str::limit($previewTitle, 68) }}</p>
                        <p class="mt-1 text-sm leading-5 text-zinc-700">{{ \Illuminate\Support\Str::limit($previewDescription, 160) }}</p>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <x-ui.card-header>
                        <x-ui.card-title>SEO audit</x-ui.card-title>
                        <span class="font-mono text-xs text-amber-400">{{ $passedChecks }}/{{ count($checks) }} passed</span>
                    </x-ui.card-header>
                    <div class="divide-y divide-zinc-800/60">
                        @foreach ($checks as $check)
                            <div class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                                <span @class([
                                    'mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-full',
                                    'bg-emerald-500/15 text-emerald-300' => $check['ok'],
                                    'bg-amber-500/15 text-amber-300' => ! $check['ok'],
                                ])>
                                    <flux:icon :name="$check['ok'] ? 'check' : 'exclamation-triangle'" class="size-3" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold">{{ $check['label'] }}</p>
                                    <p class="mt-0.5 truncate text-xs text-zinc-500">{{ $check['note'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            </aside>
        </div>
    </div>
</x-layouts.app>
