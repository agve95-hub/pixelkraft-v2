<x-layouts.app>
    <x-slot:title>Redirects — {{ $site->name }}</x-slot:title>

    <div class="max-w-4xl">
        <div class="mb-6">
            <a href="{{ route('sites.show', $site) }}" class="text-xs text-zinc-500 hover:text-violet-400 transition">← {{ $site->name }}</a>
            <h2 class="text-lg font-semibold text-zinc-100 mt-1">301 Redirects</h2>
            <p class="text-sm text-zinc-500">Manage URL redirects for {{ $site->name }}. Changes are applied to the Nginx config automatically.</p>
        </div>

        @livewire('seo.redirect-manager', ['siteId' => $site->id])
    </div>
</x-layouts.app>
