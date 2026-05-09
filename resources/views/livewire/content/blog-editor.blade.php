<div class="max-w-4xl space-y-5">
    <div class="ui-page-head">
        <div>
            <h1 class="ui-page-title">{{ $postId ? 'Edit Post' : 'New Post' }}</h1>
            <p class="ui-page-sub">Write and publish a blog post.</p>
        </div>
        <div class="flex items-center overflow-hidden rounded-md border border-zinc-700 bg-zinc-950">
            <flux:select wire:model="status" size="sm" class="w-[118px] rounded-none border-0 border-r border-zinc-700">
                <flux:select.option value="draft">Draft</flux:select.option>
                <flux:select.option value="scheduled">Scheduled</flux:select.option>
                <flux:select.option value="published">Published</flux:select.option>
            </flux:select>
            <flux:button wire:click="save" variant="primary" class="rounded-none" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $postId ? 'Update' : 'Publish' }}</span>
                <span wire:loading wire:target="save">Saving...</span>
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Main --}}
        <div class="space-y-4 lg:col-span-2">
            <input wire:model.live.debounce.300ms="title" type="text" placeholder="Post title..."
                class="w-full border-0 bg-transparent p-0 text-2xl font-bold text-zinc-100 placeholder-zinc-700 focus:outline-none focus:ring-0" />

            <div class="flex items-center gap-2">
                <span class="font-mono text-xs text-zinc-600">/blog/</span>
                <flux:input wire:model="slug" placeholder="post-slug" size="sm" class="font-mono"
                    x-on:input="$wire.set('autoSlug', false)" />
                @error('slug') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            <flux:field>
                <flux:label>Content <span class="text-xs font-normal text-zinc-500">(HTML)</span></flux:label>
                <flux:textarea wire:model.live.debounce.500ms="body" rows="20"
                    placeholder="Write HTML content here..."
                    class="min-h-[400px] resize-y font-mono text-sm" />
                <flux:error name="body" />
            </flux:field>

            <flux:field>
                <flux:label>Excerpt <x-ui.badge variant="outline" class="ml-1.5 h-4 px-1 text-xs font-normal">Optional</x-ui.badge></flux:label>
                <flux:textarea wire:model="excerpt" rows="3"
                    placeholder="Brief summary for previews and listings..." />
            </flux:field>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-4">
            <x-ui.card>
                <x-ui.card-header><x-ui.card-title>Featured Image</x-ui.card-title></x-ui.card-header>
                <flux:input wire:model="featuredImage"
                    placeholder="https://... or path/to/image.jpg" class="font-mono text-xs" />
                @if ($featuredImage)
                    <div class="mt-3 overflow-hidden rounded-lg border border-zinc-800">
                        <img src="{{ $featuredImage }}" alt="Featured" class="h-auto w-full">
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header><x-ui.card-title>Tags</x-ui.card-title></x-ui.card-header>
                <div class="flex gap-2">
                    <flux:input wire:model="tagInput" wire:keydown.enter.prevent="addTag"
                        placeholder="Add tag..." size="sm" class="flex-1" />
                    <flux:button wire:click="addTag" variant="outline" size="sm">+</flux:button>
                </div>
                @if (!empty($tags))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($tags as $index => $tag)
                            <span class="inline-flex items-center gap-1 rounded-full bg-violet-500/10 px-2.5 py-0.5 text-xs text-violet-400">
                                {{ $tag }}
                                <button wire:click="removeTag({{ $index }})" class="hover:text-violet-200">&times;</button>
                            </span>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            @if ($status === 'scheduled')
                <x-ui.card>
                    <x-ui.card-header><x-ui.card-title>Schedule</x-ui.card-title></x-ui.card-header>
                    <flux:input type="datetime-local" wire:model="scheduledAt" />
                </x-ui.card>
            @endif

            @if ($templates->isNotEmpty())
                <x-ui.card>
                    <x-ui.card-header><x-ui.card-title>Template</x-ui.card-title></x-ui.card-header>
                    <flux:select wire:model="templateId">
                        <flux:select.option value="">Default (basic HTML)</flux:select.option>
                        @foreach ($templates as $template)
                            <flux:select.option value="{{ $template->id }}">{{ $template->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </x-ui.card>
            @endif

            <x-ui.card>
                <x-ui.card-header><x-ui.card-title>Output Path</x-ui.card-title></x-ui.card-header>
                <flux:input wire:model="outputPath"
                    placeholder="blog/{{ $slug ?: 'post-slug' }}.html" class="font-mono text-xs" />
                <p class="mt-1 text-[10px] text-zinc-600">Relative to repo root.</p>
            </x-ui.card>

            <x-ui.card>
                <x-ui.card-header><x-ui.card-title>SEO</x-ui.card-title></x-ui.card-header>
                <div class="space-y-3">
                    <flux:field>
                        <flux:label>Title tag</flux:label>
                        <flux:input wire:model="seoTitle" placeholder="{{ $title }}" size="sm" />
                        <flux:description>{{ Str::length($seoTitle ?: $title) }}/70 chars</flux:description>
                    </flux:field>
                    <flux:field>
                        <flux:label>Meta description</flux:label>
                        <flux:textarea wire:model="seoDescription" rows="3" placeholder="Post description..." />
                        <flux:description>{{ Str::length($seoDescription) }}/160 chars</flux:description>
                    </flux:field>
                    <flux:field>
                        <flux:label>OG Image</flux:label>
                        <flux:input wire:model="ogImage" placeholder="https://..." class="font-mono text-xs" />
                    </flux:field>
                </div>
            </x-ui.card>
        </div>
    </div>
</div>
