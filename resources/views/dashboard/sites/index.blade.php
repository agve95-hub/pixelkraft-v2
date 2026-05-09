<x-layouts.app>
    <x-slot:title>Sites</x-slot:title>

    @php
        $sites = \App\Support\SiteAccess::query()
            ->with(['latestUptimeCheck'])
            ->withCount(['pages', 'invoices as unpaid_invoice_count' => fn ($q) => $q->where('status', 'unpaid'), 'reminders as pending_reminder_count' => fn ($q) => $q->where('is_done', false)])
            ->orderBy('name')
            ->get();
        $liveCount = $sites->filter(fn ($site) => $site->deploy_status === \App\Enums\DeployStatus::Live)->count();
        $unpaidCount = $sites->sum('unpaid_invoice_count');
        $pendingCount = $sites->sum('pending_reminder_count');
    @endphp

    <div class="space-y-5">
        <div class="ui-page-head">
            <div>
                <h1 class="ui-page-title">All sites</h1>
                <p class="ui-page-sub">{{ $sites->count() }} projects</p>
            </div>
            <x-ui.button href="{{ route('sites.create') }}" icon="plus">
                New project
            </x-ui.button>
        </div>

        <div class="stats stats-4">
            <div class="stat">
                <p class="stat-label">Projects</p>
                <p class="stat-val">{{ $sites->count() }}</p>
                <p class="stat-note">{{ $liveCount }} live</p>
            </div>
            <div class="stat">
                <p class="stat-label">Pages</p>
                <p class="stat-val">{{ number_format($sites->sum('pages_count')) }}</p>
                <p class="stat-note">Indexed across all sites</p>
            </div>
            <div class="stat">
                <p class="stat-label">Invoices</p>
                <p class="stat-val {{ $unpaidCount ? 'text-amber-400' : '' }}">{{ $unpaidCount }}</p>
                <p class="stat-note">Unpaid</p>
            </div>
            <div class="stat">
                <p class="stat-label">Reminders</p>
                <p class="stat-val {{ $pendingCount ? 'text-amber-400' : '' }}">{{ $pendingCount }}</p>
                <p class="stat-note">Pending</p>
            </div>
        </div>

        @livewire('dashboard.site-list')
    </div>
</x-layouts.app>
