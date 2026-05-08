<div>
    <form wire:submit="save" class="pk-form-stack space-y-6">
        @if (! $metaEditingSupported)
            <div class="pk-ui-alert pk-ui-alert-warning">
                {{ $metaEditingNotice }}
            </div>
        @endif

        <div class="space-y-5">
            <div class="pk-ui-card-header">
                <div>
                    <x-ui.card-title>Meta tags</x-ui.card-title>
                    <x-ui.card-description>Start with the focus keyword, title, and description. These matter most for ranking.</x-ui.card-description>
                </div>
                <x-ui.button type="button" wire:click="runAnalysis" variant="outline" size="sm" icon="arrow-path">Re-analyze</x-ui.button>
            </div>

            <div class="pk-form-grid pk-form-grid-2">
                <div>
                    <label class="mb-1.5 block">Focus keyword</label>
                    <flux:input
                        wire:model.live.debounce.300ms="focusKeyword"
                        placeholder="e.g. brand design"
                        :disabled="! $metaEditingSupported"
                    />
                    <p class="mt-1 text-xs text-zinc-500">Used for scoring guidance (title + description targeting).</p>
                </div>

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <label class="block">SEO title</label>
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
                    <p class="mt-1 text-xs text-zinc-500">Aim for 30-60 characters. This appears as the blue link in Google.</p>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block">Canonical URL</label>
                <flux:input
                    type="url"
                    wire:model="canonicalUrl"
                    placeholder="https://example.com/"
                    :disabled="! $metaEditingSupported"
                    class="font-mono"
                />
                <p class="mt-1 text-xs text-zinc-500">Use when one page should be treated as the main version (avoids duplicate content).</p>
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label class="block">Meta description</label>
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
                <p class="mt-1 text-xs text-zinc-500">Aim for 120-155 characters. This is the snippet shown below the title in search results.</p>
            </div>

            <div>
                <label class="mb-1.5 block">Keywords</label>
                <flux:input
                    wire:model="metaKeywords"
                    placeholder="keyword one, keyword two, keyword three"
                    :disabled="! $metaEditingSupported"
                />
                <p class="mt-1 text-xs text-zinc-500">Optional. Keep short and relevant. Most search engines ignore this, but some tools use it.</p>
            </div>
        </div>

        <flux:separator />

        <div class="space-y-5">
            <div>
                <x-ui.card-title>Open Graph / Social</x-ui.card-title>
                <p class="pk-page-sub">Controls how the page appears when shared on social platforms, Slack, Discord, etc.</p>
            </div>

            <div class="pk-form-grid pk-form-grid-2">
                <div>
                    <label class="mb-1.5 block">Social title</label>
                    <flux:input
                        wire:model.live="ogTitle"
                        placeholder="{{ $title ?: 'Same as SEO title' }}"
                        :disabled="! $metaEditingSupported"
                    />
                    <p class="mt-1 text-xs text-zinc-500">og:title. Leave blank to use the SEO title.</p>
                </div>

                <div>
                    <label class="mb-1.5 block">Social image URL</label>
                    <flux:input
                        wire:model.live="ogImage"
                        placeholder="images/og-image.svg"
                        :disabled="! $metaEditingSupported"
                        class="font-mono"
                    />
                    <p class="mt-1 text-xs text-zinc-500">og:image. Recommended 1200 x 630px.</p>
                </div>
            </div>

            <div>
                <label class="mb-1.5 block">Social description</label>
                <flux:textarea
                    wire:model.live="ogDescription"
                    rows="3"
                    placeholder="{{ $metaDescription ?: 'Same as meta description' }}"
                    :disabled="! $metaEditingSupported"
                />
                <p class="mt-1 text-xs text-zinc-500">og:description. Leave blank to use the meta description.</p>
            </div>
        </div>

        <div class="pk-action-row border-t border-zinc-800 pt-4">
            <x-ui.button type="submit" variant="default" icon="bookmark" :disabled="! $metaEditingSupported">Save page SEO</x-ui.button>
        </div>
    </form>
</div>
