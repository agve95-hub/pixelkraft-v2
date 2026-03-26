<div class="max-w-4xl">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-zinc-100">{{ $postId ? 'Edit Post' : 'New Post' }}</h2>
            <p class="text-sm text-zinc-500">Write and publish a blog post.</p>
        </div>
        <div class="flex items-center gap-2">
            <select wire:model="status" class="flux-input text-sm w-auto">
                <option value="draft">Draft</option>
                <option value="scheduled">Scheduled</option>
                <option value="published">Published</option>
            </select>
            <button wire:click="save" class="flux-btn-primary text-sm" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $postId ? 'Update' : 'Publish' }}</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content --}}
        <div class="lg:col-span-2 space-y-5">
            {{-- Title --}}
            <div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="title"
                    class="w-full bg-transparent border-0 text-2xl font-bold text-zinc-100 placeholder-zinc-700 focus:outline-none focus:ring-0 p-0"
                    placeholder="Post title..."
                >
            </div>

            {{-- Slug --}}
            <div class="flex items-center gap-2">
                <span class="mono text-xs text-zinc-600">/blog/</span>
                <input
                    type="text"
                    wire:model="slug"
                    class="flex-1 bg-transparent border-0 border-b border-zinc-800 mono text-xs text-zinc-400 placeholder-zinc-700 focus:outline-none focus:ring-0 focus:border-violet-500 px-0 py-1"
                    placeholder="post-slug"
                    x-on:input="$wire.set('autoSlug', false)"
                >
                @error('slug') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
            </div>

            {{-- Body --}}
            <div>
                <label class="flux-label">Content</label>
                <textarea
                    wire:model.live.debounce.500ms="body"
                    rows="20"
                    class="flux-input text-sm resize-y min-h-[300px]"
                    placeholder="Write your post content here... HTML is supported."
                ></textarea>
                @error('body') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            {{-- Excerpt --}}
            <div>
                <label class="flux-label">Excerpt <span class="text-zinc-600 font-normal">(optional)</span></label>
                <textarea
                    wire:model="excerpt"
                    rows="3"
                    class="flux-input text-sm resize-y"
                    placeholder="Brief summary for previews and listings..."
                ></textarea>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-5">
            {{-- Featured Image --}}
            <div class="card">
                <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider mb-3">Featured Image</h3>
                <input
                    type="text"
                    wire:model="featuredImage"
                    class="flux-input text-xs mono"
                    placeholder="https://... or path/to/image.jpg"
                >
                @if ($featuredImage)
                    <div class="mt-3 rounded-lg border border-zinc-800 overflow-hidden">
                        <img src="{{ $featuredImage }}" alt="Featured" class="w-full h-auto">
                    </div>
                @endif
            </div>

            {{-- Tags --}}
            <div class="card">
                <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider mb-3">Tags</h3>
                <div class="flex gap-2">
                    <input
                        type="text"
                        wire:model="tagInput"
                        wire:keydown.enter.prevent="addTag"
                        class="flux-input text-xs flex-1"
                        placeholder="Add tag..."
                    >
                    <button wire:click="addTag" class="flux-btn-secondary text-xs !py-1.5 !px-2">+</button>
                </div>
                @if (!empty($tags))
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        @foreach ($tags as $index => $tag)
                            <span class="inline-flex items-center gap-1 rounded-full bg-violet-500/10 px-2.5 py-0.5 text-xs text-violet-400">
                                {{ $tag }}
                                <button wire:click="removeTag({{ $index }})" class="hover:text-violet-200">×</button>
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Schedule --}}
            @if ($status === 'scheduled')
                <div class="card">
                    <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider mb-3">Schedule</h3>
                    <input
                        type="datetime-local"
                        wire:model="scheduledAt"
                        class="flux-input text-sm"
                    >
                </div>
            @endif

            {{-- Template --}}
            @if ($templates->isNotEmpty())
                <div class="card">
                    <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider mb-3">Template</h3>
                    <select wire:model="templateId" class="flux-input text-sm">
                        <option value="">Default (basic HTML)</option>
                        @foreach ($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Output Path --}}
            <div class="card">
                <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider mb-3">Output Path</h3>
                <input
                    type="text"
                    wire:model="outputPath"
                    class="flux-input text-xs mono"
                    placeholder="blog/{{ $slug ?: 'post-slug' }}.html"
                >
                <p class="mt-1 text-[10px] text-zinc-600">Relative to repo root. Where the generated HTML is saved.</p>
            </div>

            {{-- SEO --}}
            <div class="card">
                <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider mb-3">SEO</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-[10px] uppercase tracking-wider text-zinc-600 mb-1 block">Title tag</label>
                        <input type="text" wire:model="seoTitle" class="flux-input text-xs" placeholder="{{ $title }}">
                        <p class="mt-0.5 text-[10px] text-zinc-600 mono">{{ Str::length($seoTitle ?: $title) }}/70</p>
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wider text-zinc-600 mb-1 block">Meta description</label>
                        <textarea wire:model="seoDescription" rows="3" class="flux-input text-xs resize-y" placeholder="Post description..."></textarea>
                        <p class="mt-0.5 text-[10px] text-zinc-600 mono">{{ Str::length($seoDescription) }}/160</p>
                    </div>
                    <div>
                        <label class="text-[10px] uppercase tracking-wider text-zinc-600 mb-1 block">OG Image</label>
                        <input type="text" wire:model="ogImage" class="flux-input text-xs mono" placeholder="https://...">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
