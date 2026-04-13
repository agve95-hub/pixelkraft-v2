<x-layouts.app>
    <x-slot:title>{{ $site->name }} — Maintenance</x-slot:title>

    <div class="space-y-6 text-zinc-100">
        <a href="{{ route('sites.show', $site) }}" class="inline-flex items-center gap-1.5 text-[13px] text-zinc-500 transition hover:text-zinc-200">
            <flux:icon name="chevron-left" class="size-3.5" />
            {{ $site->name }}
        </a>

        @livewire('sites.maintenance-mode', ['siteId' => $site->id], key('maintenance-' . $site->id))
    </div>
</x-layouts.app>
