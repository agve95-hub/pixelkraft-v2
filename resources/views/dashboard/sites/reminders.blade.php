<x-layouts.app>
    <x-slot:title>Reminders — {{ $site->name }}</x-slot:title>
    <div class="space-y-5">
        <div class="pk-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="pk-page-title">Reminders</h1>
                <p class="pk-page-sub">Follow-ups, deadlines, and action items for {{ $site->name }}.</p>
            </div>
        </div>
        @livewire('sites.reminder-manager', ['siteId' => $site->id], key('reminders-' . $site->id))
    </div>
</x-layouts.app>
