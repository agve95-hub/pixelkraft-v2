<x-layouts.app>
    <x-slot:title>SEO - {{ $page->title ?? $page->file_path }}</x-slot:title>

    @php
        $editorSupport = app(\App\Services\SiteSupportService::class)->editorProfile($site, $page);
        $seoScore = (int) ($page->seo_score ?? 0);
        $scoreTone = match (true) {
            $seoScore >= 80 => 'pill-green',
            $seoScore >= 50 => 'pill-yellow',
            default => 'pill-red',
        };
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

    <div class="space-y-6" x-data="{ tab: 'meta' }">
        <div class="pk-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="mb-2 inline-flex items-center gap-1 text-xs text-zinc-500 transition hover:text-zinc-300">
                    <flux:icon name="chevron-left" class="size-3.5" />
                    {{ $site->name }}
                </a>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="pk-page-title">SEO Settings</h1>
                    <span class="pill {{ $scoreTone }}">{{ $seoScore }}/100</span>
                </div>
                <p class="pk-page-sub">{{ $site->name }} &middot; {{ $previewTitle }} <span class="rounded bg-zinc-800 px-1.5 py-0.5 font-mono text-[11px] text-zinc-400">{{ $pagePath }}</span></p>
            </div>
            <div class="pk-ui-button-group">
                <x-ui.button type="button" variant="outline" size="sm" x-on:click="tab = 'meta'">Run audit</x-ui.button>
                <x-ui.button href="{{ route('editor', ['site' => $site, 'page' => $page]) }}" variant="default" size="sm" icon="pencil-square">Open editor</x-ui.button>
            </div>
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
                <p class="mt-3"><span class="pill {{ $page->is_published ? 'pill-green' : 'pill-yellow' }}">{{ $page->is_published ? 'Published' : 'Draft' }}</span></p>
            </div>
        </div>

        @if ($editorSupport['meta_editing_mode'] === 'unsupported')
            <div class="pk-ui-alert pk-ui-alert-warning">
                <flux:icon name="information-circle" class="size-4" />
                <div>
                    <p class="pk-ui-alert-title">Code first</p>
                    <p class="pk-ui-alert-body">{{ $editorSupport['meta_notice'] }}</p>
                </div>
            </div>
        @endif

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)]">
            <section class="space-y-5">
                <div class="tab-bar">
                    <button type="button" x-on:click="tab = 'meta'" x-bind:class="{ 'active': tab === 'meta' }" class="tab">Meta tags</button>
                    <button type="button" x-on:click="tab = 'schema'" x-bind:class="{ 'active': tab === 'schema' }" class="tab">Structured data</button>
                    <button type="button" x-on:click="tab = 'robots'" x-bind:class="{ 'active': tab === 'robots' }" class="tab">Robots</button>
                </div>

                <div x-show="tab === 'meta'" x-cloak class="pk-ui-card">
                    @livewire('seo.meta-editor', ['pageId' => $page->id], key('seo-meta-' . $page->id))
                </div>

                <div x-show="tab === 'schema'" x-cloak class="space-y-5">
                    <section class="pk-ui-card">
                        <div class="dash-card-head">
                            <div>
                                <h2 class="dash-card-title">Structured data (JSON-LD)</h2>
                                <p class="pk-page-sub">Describe this page as an article, FAQ, product, or local business entity.</p>
                            </div>
                        </div>
                        @livewire('seo.schema-editor', ['pageId' => $page->id], key('seo-schema-' . $page->id))
                    </section>
                </div>

                <div x-show="tab === 'robots'" x-cloak class="space-y-5">
                    <section class="pk-ui-card">
                        <div class="dash-card-head">
                            <div>
                                <h2 class="dash-card-title">Site-wide robots.txt</h2>
                                <p class="pk-page-sub">This file affects the whole site, not only <span class="font-mono text-zinc-300">{{ $pagePath }}</span>.</p>
                            </div>
                        </div>
                        @livewire('seo.robots-txt-editor', ['siteId' => $site->id], key('seo-robots-' . $site->id))
                    </section>
                </div>
            </section>

            <aside class="space-y-5">
                <section class="pk-ui-card">
                    <div class="dash-card-head">
                        <h2 class="dash-card-title">Search result preview</h2>
                    </div>
                    <div class="rounded-lg bg-white px-4 py-3 text-zinc-900">
                        <p class="font-mono text-[11px] text-zinc-700">{{ $canonicalUrl }}</p>
                        <p class="mt-1 text-lg leading-tight text-blue-700">{{ \Illuminate\Support\Str::limit($previewTitle, 68) }}</p>
                        <p class="mt-1 text-sm leading-5 text-zinc-700">{{ \Illuminate\Support\Str::limit($previewDescription, 160) }}</p>
                    </div>
                </section>

                <section class="pk-ui-card">
                    <div class="dash-card-head">
                        <h2 class="dash-card-title">SEO audit</h2>
                        <span class="font-mono text-xs text-amber-400">{{ $passedChecks }}/{{ count($checks) }} passed</span>
                    </div>
                    <div class="divide-y divide-zinc-800/90">
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
                                    <p class="text-sm font-semibold text-zinc-100">{{ $check['label'] }}</p>
                                    <p class="mt-0.5 truncate text-xs text-zinc-500">{{ $check['note'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>
    </div>
</x-layouts.app>
