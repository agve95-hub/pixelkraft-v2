<div>
    <form wire:submit="save" class="space-y-8">
        @if (! $metaEditingSupported)
            <div class="rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 text-sm text-amber-800 dark:text-amber-100">
                {{ $metaEditingNotice }}
            </div>
        @endif

        {{-- Page SEO section --}}
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Page SEO</h2>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Start with the focus keyword, title, and description — these matter most for ranking.</p>
                </div>
                <button type="button" wire:click="runAnalysis" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition">
                    <flux:icon name="arrow-path" class="size-3.5" />
                    Re-analyze
                </button>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Focus keyword</label>
                    <flux:input
                        wire:model.live.debounce.300ms="focusKeyword"
                        placeholder="e.g. brand design"
                        :disabled="! $metaEditingSupported"
                    />
                    <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">Used for scoring guidance (title + description targeting).</p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">SEO title</label>
                        <span @class([
                            'text-xs tabular-nums font-mono',
                            'text-emerald-500' => mb_strlen($title) >= 30 && mb_strlen($title) <= 60,
                            'text-red-500' => mb_strlen($title) > 0 && (mb_strlen($title) < 30 || mb_strlen($title) > 60),
                            'text-zinc-400' => mb_strlen($title) === 0,
                        ])>{{ mb_strlen($title) }}/60</span>
                    </div>
                    <flux:input
                        wire:model.live="title"
                        placeholder="Page title shown in search results"
                        :disabled="! $metaEditingSupported"
                    />
                    <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">Aim for 30–60 characters. This appears as the blue link in Google.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Canonical URL</label>
                <flux:input
                    type="url"
                    wire:model="canonicalUrl"
                    placeholder="https://example.com/"
                    :disabled="! $metaEditingSupported"
                    class="font-mono"
                />
                <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">Use when one page should be treated as the main version (avoids duplicate content).</p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Meta description</label>
                    <span @class([
                        'text-xs tabular-nums font-mono',
                        'text-emerald-500' => mb_strlen($metaDescription) >= 120 && mb_strlen($metaDescription) <= 155,
                        'text-red-500' => mb_strlen($metaDescription) > 0 && (mb_strlen($metaDescription) < 120 || mb_strlen($metaDescription) > 155),
                        'text-zinc-400' => mb_strlen($metaDescription) === 0,
                    ])>{{ mb_strlen($metaDescription) }}/155</span>
                </div>
                <flux:textarea
                    wire:model.live="metaDescription"
                    rows="3"
                    placeholder="A clear summary that tells people what this page is about."
                    :disabled="! $metaEditingSupported"
                />
                <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">Aim for 120–155 characters. This is the snippet shown below the title in search results.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Keywords</label>
                <flux:input
                    wire:model="metaKeywords"
                    placeholder="keyword one, keyword two, keyword three"
                    :disabled="! $metaEditingSupported"
                />
                <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">Optional. Keep short and relevant — most search engines ignore this, but some tools use it.</p>
            </div>
        </div>

        <flux:separator />

        {{-- Social sharing section --}}
        <div class="space-y-5">
            <div>
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">Social sharing</h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Controls how the page appears when shared on social platforms, Slack, Discord, etc.</p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Social title</label>
                    <flux:input
                        wire:model.live="ogTitle"
                        placeholder="{{ $title ?: 'Same as SEO title' }}"
                        :disabled="! $metaEditingSupported"
                    />
                    <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">og:title — leave blank to use the SEO title.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Social image URL</label>
                    <flux:input
                        wire:model.live="ogImage"
                        placeholder="images/og-image.svg"
                        :disabled="! $metaEditingSupported"
                        class="font-mono"
                    />
                    <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">og:image — recommended 1200 &times; 630px</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5">Social description</label>
                <flux:textarea
                    wire:model.live="ogDescription"
                    rows="3"
                    placeholder="{{ $metaDescription ?: 'Same as meta description' }}"
                    :disabled="! $metaEditingSupported"
                />
                <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">og:description — leave blank to use the meta description.</p>
            </div>
        </div>

        {{-- Bottom actions --}}
        <div class="flex items-center justify-center gap-4 pt-2 pb-4">
            <flux:button type="submit" variant="primary" icon="bookmark" :disabled="! $metaEditingSupported">
                Save page SEO
            </flux:button>
            <button type="button" wire:click="runAnalysis" class="inline-flex items-center gap-1.5 text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition">
                <flux:icon name="arrow-path" class="size-3.5" />
                Re-analyze
            </button>
        </div>
    </form>
</div>
