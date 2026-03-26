<x-layouts.app>
    <x-slot:title>Sites</x-slot:title>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">All Sites</flux:heading>
                <flux:subheading>Manage your websites</flux:subheading>
            </div>
            <flux:button href="{{ route('sites.create') }}" variant="primary" icon="plus" size="sm">Add site</flux:button>
        </div>

        @livewire('dashboard.site-list')
    </div>
</x-layouts.app>
