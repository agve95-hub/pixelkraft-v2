<x-layouts.app>
    <x-slot:title>Pages — {{ $site->name }}</x-slot:title>
    <div>
        <div class="mb-6">
            <a href="{{ route('sites.show', $site) }}" class="text-xs text-zinc-500 hover:text-violet-400 transition">← {{ $site->name }}</a>
            <h2 class="text-lg font-semibold text-zinc-100 mt-1">Pages</h2>
            <p class="text-sm text-zinc-500">Manage, edit, and configure SEO for all pages on this site.</p>
        </div>
        @livewire('sites.page-listing', ['siteId' => $site->id])
    </div>
</x-layouts.app>
