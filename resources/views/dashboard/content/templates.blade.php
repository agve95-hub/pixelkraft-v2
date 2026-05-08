<x-layouts.app>
    <x-slot:title>Templates — {{ $site->name }}</x-slot:title>
    <div class="space-y-5">
        <div class="pk-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="pk-page-title">Content Templates</h1>
                <p class="pk-page-sub">Reusable HTML templates with <code class="font-mono text-zinc-400">&#123;&#123;placeholder&#125;&#125;</code> variables.</p>
            </div>
        </div>
        @livewire('content.template-manager', ['siteId' => $site->id])
    </div>
</x-layouts.app>
