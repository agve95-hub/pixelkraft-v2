<x-layouts.app>
    <x-slot:title>Expenses — {{ $site->name }}</x-slot:title>
    <div class="space-y-5">
        <div class="pk-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="pk-page-title">Expenses</h1>
                <p class="pk-page-sub">Costs and spending tracked for {{ $site->name }}.</p>
            </div>
        </div>
        @livewire('sites.expense-manager', ['siteId' => $site->id], key('expenses-' . $site->id))
    </div>
</x-layouts.app>
