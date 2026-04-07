<div class="grid gap-6 xl:grid-cols-[1.2fr,0.8fr]">
    <form wire:submit="save" class="space-y-6">
        @if (! $metaEditingSupported)
            <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 px-4 py-4 text-sm text-amber-100">
                {{ $metaEditingNotice }}
            </div>
        @endif

        <div class="card space-y-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-100">Page SEO</h2>
                    <p class="mt-1 text-sm text-zinc-400">
                        Start with the search result title and description. Those are the two fields that matter most.
                    </p>
                </div>

                <div class="flex gap-3">
                    <button type="button" wire:click="runAnalysis" class="flux-btn-secondary text-sm">Re-analyze</button>
                    <button type="submit" class="flux-btn-primary text-sm" @disabled(! $metaEditingSupported)>Save Page SEO</button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="flux-label">SEO title</label>
                    <input
                        type="text"
                        wire:model.live="title"
                        class="flux-input text-sm"
                        placeholder="Page title that people see in search results"
                        @disabled(! $metaEditingSupported)
                    >
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <p class="text-zinc-500">Aim for 30 to 60 characters.</p>
                        <span @class([
                            'mono',
                            'text-emerald-500' => mb_strlen($title) >= 30 && mb_strlen($title) <= 60,
                            'text-amber-500' => mb_strlen($title) > 0 && (mb_strlen($title) < 30 || mb_strlen($title) > 60),
                            'text-zinc-600' => mb_strlen($title) === 0,
                        ])>{{ mb_strlen($title) }}/60</span>
                    </div>
                </div>

                <div>
                    <label class="flux-label">Canonical URL</label>
                    <input
                        type="url"
                        wire:model="canonicalUrl"
                        class="flux-input text-sm mono"
                        placeholder="https://example.com/page"
                        @disabled(! $metaEditingSupported)
                    >
                    <p class="mt-2 text-xs text-zinc-500">Use this when one page should be treated as the main version.</p>
                </div>
            </div>

            <div>
                <label class="flux-label">Meta description</label>
                <textarea
                    wire:model.live="metaDescription"
                    rows="4"
                    class="flux-input text-sm resize-y"
                    placeholder="A clear summary that tells people what this page is about."
                    @disabled(! $metaEditingSupported)
                ></textarea>
                <div class="mt-2 flex items-center justify-between text-xs">
                    <p class="text-zinc-500">Aim for 120 to 155 characters.</p>
                    <span @class([
                        'mono',
                        'text-emerald-500' => mb_strlen($metaDescription) >= 120 && mb_strlen($metaDescription) <= 155,
                        'text-amber-500' => mb_strlen($metaDescription) > 0 && (mb_strlen($metaDescription) < 120 || mb_strlen($metaDescription) > 155),
                        'text-zinc-600' => mb_strlen($metaDescription) === 0,
                    ])>{{ mb_strlen($metaDescription) }}/155</span>
                </div>
            </div>

            <div>
                <label class="flux-label">Keywords</label>
                <input
                    type="text"
                    wire:model="metaKeywords"
                    class="flux-input text-sm"
                    placeholder="keyword one, keyword two, keyword three"
                    @disabled(! $metaEditingSupported)
                >
                <p class="mt-2 text-xs text-zinc-500">Optional. Keep this short and useful.</p>
            </div>
        </div>

        <div class="card space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100">Social sharing</h2>
                <p class="mt-1 text-sm text-zinc-400">
                    These fields control how the page looks when someone shares it in chat apps or on social platforms.
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <label class="flux-label">Social title</label>
                    <input
                        type="text"
                        wire:model.live="ogTitle"
                        class="flux-input text-sm"
                        placeholder="{{ $title ?: 'Use the SEO title' }}"
                        @disabled(! $metaEditingSupported)
                    >
                    <p class="mt-2 text-xs text-zinc-500">Leave blank to fall back to the SEO title.</p>
                </div>

                <div>
                    <label class="flux-label">Social image URL</label>
                    <input
                        type="url"
                        wire:model.live="ogImage"
                        class="flux-input text-sm mono"
                        placeholder="https://example.com/og-image.jpg"
                        @disabled(! $metaEditingSupported)
                    >
                    <p class="mt-2 text-xs text-zinc-500">Best size: 1200 x 630.</p>
                </div>
            </div>

            <div>
                <label class="flux-label">Social description</label>
                <textarea
                    wire:model.live="ogDescription"
                    rows="3"
                    class="flux-input text-sm resize-y"
                    placeholder="{{ $metaDescription ?: 'Use the SEO description' }}"
                    @disabled(! $metaEditingSupported)
                ></textarea>
                <p class="mt-2 text-xs text-zinc-500">Leave blank to fall back to the meta description.</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="flux-btn-primary text-sm" @disabled(! $metaEditingSupported)>Save Page SEO</button>
                <button type="button" wire:click="runAnalysis" class="flux-btn-secondary text-sm">Re-analyze</button>
            </div>
        </div>
    </form>

    <div class="space-y-6">
        <div class="card space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-100">SEO health</h2>
                    <p class="mt-1 text-sm text-zinc-400">Use this to see what matters next, not every possible metric at once.</p>
                </div>

                <div class="text-right">
                    <p @class([
                        'text-3xl font-bold mono',
                        'text-emerald-400' => ($analysis['score'] ?? 0) >= 80,
                        'text-amber-400' => ($analysis['score'] ?? 0) >= 50 && ($analysis['score'] ?? 0) < 80,
                        'text-red-400' => ($analysis['score'] ?? 0) < 50,
                    ])>{{ $analysis['score'] ?? 0 }}/100</p>
                    <p class="text-xs text-zinc-500">Current score</p>
                </div>
            </div>

            <div class="h-2 rounded-full bg-zinc-800 overflow-hidden">
                <div
                    @class([
                        'h-full rounded-full transition-all duration-500',
                        'bg-emerald-500' => ($analysis['score'] ?? 0) >= 80,
                        'bg-amber-500' => ($analysis['score'] ?? 0) >= 50 && ($analysis['score'] ?? 0) < 80,
                        'bg-red-500' => ($analysis['score'] ?? 0) < 50,
                    ])
                    style="width: {{ $analysis['score'] ?? 0 }}%"
                ></div>
            </div>

            @if (!empty($analysis['suggestions']))
                <div class="space-y-2">
                    @foreach ($analysis['suggestions'] as $suggestion)
                        <div @class([
                            'rounded-xl border px-3 py-3 text-sm',
                            'border-red-500/20 bg-red-500/5 text-red-300' => $suggestion['severity'] === 'error',
                            'border-amber-500/20 bg-amber-500/5 text-amber-200' => $suggestion['severity'] === 'warning',
                            'border-blue-500/20 bg-blue-500/5 text-blue-200' => $suggestion['severity'] === 'info',
                        ])>
                            {{ $suggestion['message'] }}
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-3 py-3 text-sm text-emerald-300">
                    All core SEO checks passed.
                </div>
            @endif
        </div>

        <div class="card space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100">Search preview</h2>
                <p class="mt-1 text-sm text-zinc-400">This is roughly how your page can appear in Google.</p>
            </div>

            <div class="rounded-xl bg-white p-4">
                <p class="truncate text-sm leading-snug text-[#1a0dab]" style="font-family: Arial, sans-serif;">
                    {{ $title ?: 'Page title' }}
                </p>
                <p class="mt-1 truncate text-xs text-[#006621]" style="font-family: Arial, sans-serif;">
                    {{ $page->site?->domain ?? 'example.com' }}{{ $page->url_path ?? '/' }}
                </p>
                <p class="mt-2 text-xs leading-5 text-[#545454]" style="font-family: Arial, sans-serif;">
                    {{ $metaDescription ?: 'Add a meta description to control how this page appears in search results.' }}
                </p>
            </div>
        </div>

        <div class="card space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-zinc-100">Social preview</h2>
                <p class="mt-1 text-sm text-zinc-400">This is the card people usually see when the page gets shared.</p>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-950">
                @if ($ogImage)
                    <div class="h-48 overflow-hidden bg-zinc-900">
                        <img src="{{ $ogImage }}" alt="" class="h-full w-full object-cover">
                    </div>
                @else
                    <div class="flex h-48 items-center justify-center bg-zinc-900 text-sm text-zinc-500">
                        No social image set yet
                    </div>
                @endif

                <div class="space-y-1 p-4">
                    <p class="text-[11px] uppercase tracking-wider text-zinc-500">{{ $page->site?->domain ?? 'example.com' }}</p>
                    <p class="text-base font-medium text-zinc-100">{{ $ogTitle ?: $title ?: 'Page title' }}</p>
                    <p class="text-sm leading-6 text-zinc-400">{{ $ogDescription ?: $metaDescription ?: 'Add a social description for better sharing previews.' }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
