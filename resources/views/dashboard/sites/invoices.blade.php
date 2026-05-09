<x-layouts.app>
    <x-slot:title>Invoices — {{ $site->name }}</x-slot:title>
    <div class="space-y-5">
        <div class="ui-page-head">
            <div>
                <a href="{{ route('sites.show', $site) }}" class="back-link">
                    <flux:icon name="chevron-left" class="size-3.5" /> {{ $site->name }}
                </a>
                <h1 class="ui-page-title">Invoices</h1>
                <p class="ui-page-sub">Billing and payment records for {{ $site->clientDisplayName() }}.</p>
            </div>
        </div>
        @livewire('sites.invoice-manager', ['siteId' => $site->id], key('invoices-' . $site->id))
    </div>
</x-layouts.app>
