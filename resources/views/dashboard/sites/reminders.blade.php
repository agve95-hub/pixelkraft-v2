<x-layouts.app>
    <x-slot:title>{{ $site->name }} — Reminders</x-slot:title>

    @livewire('sites.reminder-manager', ['siteId' => $site->id], key('reminders-' . $site->id))
</x-layouts.app>
