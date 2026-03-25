<div class="space-y-6">
    {{-- SEO Score --}}
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-zinc-200">SEO Score</h3>
            <span @class([
                'text-2xl font-bold mono',
                'text-emerald-400' => ($analysis['score'] ?? 0) >= 80,
                'text-amber-400'   => ($analysis['score'] ?? 0) >= 50 && ($analysis['score'] ?? 0) < 80,
                'text-red-400'     => ($analysis['score'] ?? 0) < 50,
            ])>{{ $analysis['score'] ?? 0 }}/100</span>
        </div>

        {{-- Score bar --}}
        <div class="h-2 bg-zinc-800 rounded-full overflow-hidden mb-4">
            <div
                @class([
                    'h-full rounded-full transition-all duration-500',
                    'bg-emerald-500' => ($analysis['score'] ?? 0) >= 80,
                    'bg-amber-500'   => ($analysis['score'] ?? 0) >= 50 && ($analysis['score'] ?? 0) < 80,
                    'bg-red-500'     => ($analysis['score'] ?? 0) < 50,
                ])
                style="width: {{ $analysis['score'] ?? 0 }}%"
            ></div>
        </div>

        {{-- Suggestions --}}
        @if (!empty($analysis['suggestions']))
            <div class="space-y-2">
                @foreach ($analysis['suggestions'] as $suggestion)
                    <div @class([
                        'flex items-start gap-2 rounded-lg px-3 py-2 text-xs',
                        'bg-red-500/5 border border-red-500/20 text-red-400' => $suggestion['severity'] === 'error',
                        'bg-amber-500/5 border border-amber-500/20 text-amber-400' => $suggestion['severity'] === 'warning',
                        'bg-blue-500/5 border border-blue-500/20 text-blue-400' => $suggestion['severity'] === 'info',
                    ])>
                        @switch($suggestion['severity'])
                            @case('error')
                                <svg class="h-3.5 w-3.5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
                                @break
                            @case('warning')
                                <svg class="h-3.5 w-3.5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126Z" /></svg>
                                @break
                            @default
                                <svg class="h-3.5 w-3.5 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                        @endswitch
                        <span>{{ $suggestion['message'] }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-xs text-emerald-400 flex items-center gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                All SEO checks passed!
            </p>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Google Preview --}}
        <div class="card">
            <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider mb-3">Google Search Preview</h3>
            <div class="rounded-lg bg-white p-4">
                <p class="text-sm text-[#1a0dab] leading-snug truncate" style="font-family: Arial, sans-serif;">
                    {{ $title ?: 'Page Title' }}
                </p>
                <p class="text-xs text-[#006621] mt-0.5 truncate" style="font-family: Arial, sans-serif;">
                    {{ $page->site?->domain ?? 'example.com' }}{{ $page->url_path ?? '/' }}
                </p>
                <p class="text-xs text-[#545454] mt-1 line-clamp-2" style="font-family: Arial, sans-serif;">
                    {{ $metaDescription ?: 'Add a meta description to control how this page appears in search results.' }}
                </p>
            </div>
        </div>

        {{-- Social Preview --}}
        <div class="card">
            <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider mb-3">Social Share Preview</h3>
            <div class="rounded-lg border border-zinc-700 overflow-hidden max-w-sm">
                @if ($ogImage)
                    <div class="bg-zinc-800 h-40 flex items-center justify-center overflow-hidden">
                        <img src="{{ $ogImage }}" alt="" class="w-full h-full object-cover">
                    </div>
                @else
                    <div class="bg-zinc-800 h-40 flex items-center justify-center">
                        <span class="text-xs text-zinc-600">No og:image set</span>
                    </div>
                @endif
                <div class="p-3 bg-zinc-800/50">
                    <p class="text-[10px] text-zinc-500 uppercase">{{ $page->site?->domain ?? 'example.com' }}</p>
                    <p class="text-sm text-zinc-200 font-medium mt-0.5 truncate">{{ $ogTitle ?: $title ?: 'Page Title' }}</p>
                    <p class="text-xs text-zinc-400 mt-0.5 line-clamp-2">{{ $ogDescription ?: $metaDescription ?: '' }}</p>
                </div>
            </div>
        </div>

        {{-- Title --}}
        <div class="card space-y-4">
            <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider">Basic Meta</h3>

            <div>
                <label class="input-label">Title Tag</label>
                <input type="text" wire:model.live="title" class="input-field text-sm" placeholder="Page Title — Brand Name">
                <div class="flex justify-between mt-1">
                    <p class="text-[10px] text-zinc-600">Recommended: 30-60 characters</p>
                    <span @class([
                        'mono text-[10px]',
                        'text-emerald-500' => mb_strlen($title) >= 30 && mb_strlen($title) <= 60,
                        'text-amber-500'   => mb_strlen($title) > 0 && (mb_strlen($title) < 30 || mb_strlen($title) > 60),
                        'text-zinc-600'    => mb_strlen($title) === 0,
                    ])>{{ mb_strlen($title) }}/60</span>
                </div>
            </div>

            <div>
                <label class="input-label">Meta Description</label>
                <textarea wire:model.live="metaDescription" rows="3" class="input-field text-sm resize-y" placeholder="A compelling description of this page..."></textarea>
                <div class="flex justify-between mt-1">
                    <p class="text-[10px] text-zinc-600">Recommended: 120-155 characters</p>
                    <span @class([
                        'mono text-[10px]',
                        'text-emerald-500' => mb_strlen($metaDescription) >= 120 && mb_strlen($metaDescription) <= 155,
                        'text-amber-500'   => mb_strlen($metaDescription) > 0 && (mb_strlen($metaDescription) < 120 || mb_strlen($metaDescription) > 155),
                        'text-zinc-600'    => mb_strlen($metaDescription) === 0,
                    ])>{{ mb_strlen($metaDescription) }}/155</span>
                </div>
            </div>

            <div>
                <label class="input-label">Keywords <span class="text-zinc-600 font-normal">(comma separated)</span></label>
                <input type="text" wire:model="metaKeywords" class="input-field text-sm" placeholder="keyword1, keyword2, keyword3">
            </div>

            <div>
                <label class="input-label">Canonical URL</label>
                <input type="url" wire:model="canonicalUrl" class="input-field text-sm mono" placeholder="https://example.com/page">
            </div>
        </div>

        {{-- Open Graph --}}
        <div class="card space-y-4">
            <h3 class="text-xs font-semibold text-zinc-200 uppercase tracking-wider">Open Graph / Social</h3>

            <div>
                <label class="input-label">OG Title</label>
                <input type="text" wire:model.live="ogTitle" class="input-field text-sm" placeholder="{{ $title }}">
                <p class="text-[10px] text-zinc-600 mt-1">Leave empty to use the page title.</p>
            </div>

            <div>
                <label class="input-label">OG Description</label>
                <textarea wire:model.live="ogDescription" rows="2" class="input-field text-sm resize-y" placeholder="{{ $metaDescription }}"></textarea>
            </div>

            <div>
                <label class="input-label">OG Image URL</label>
                <input type="url" wire:model.live="ogImage" class="input-field text-sm mono" placeholder="https://...">
                <p class="text-[10px] text-zinc-600 mt-1">Recommended: 1200x630 pixels</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary text-sm">Save & Push to GitHub</button>
            <button type="button" wire:click="runAnalysis" class="btn-secondary text-sm">Re-analyze</button>
        </div>
    </form>
</div>
