<x-layouts.app>
    <x-slot:title>Dashboard</x-slot:title>

    @php
        $user = auth()->user();
        $hour = (int) now()->format('H');
        $greeting = match(true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default    => 'Good evening',
        };

        $totalSites = \App\Models\Site::count();
        $totalPages = \App\Models\Page::count();
        $seoIssueCount = \App\Models\Page::where(function ($q) {
            $q->whereNull('title')
              ->orWhereNull('meta_description')
              ->orWhere('seo_score', '<', 60);
        })->count();

        $latestChecks = \App\Models\UptimeCheck::query()
            ->select('site_id', \Illuminate\Support\Facades\DB::raw('MAX(checked_at) as last_check'))
            ->groupBy('site_id')
            ->get()
            ->pluck('last_check', 'site_id');

        $uptimePercent = 0;
        if ($latestChecks->isNotEmpty()) {
            $upCount = \App\Models\UptimeCheck::query()
                ->whereIn(\Illuminate\Support\Facades\DB::raw("CONCAT(site_id, '|', checked_at)"),
                    $latestChecks->map(fn ($date, $id) => "{$id}|{$date}")->values()
                )->where('is_up', true)->count();
            $uptimePercent = round(($upCount / max(1, $latestChecks->count())) * 100, 1);
        }

        $unreadMessages = \App\Models\FormSubmission::where('is_read', false)->where('is_spam', false)->count();
        $errorCount = \App\Models\Notification::where('is_read', false)
            ->whereIn('type', ['deploy_failed', 'uptime_down', 'ssl_expiring'])
            ->count();
    @endphp

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $greeting }}, {{ $user->name }}</flux:heading>
                <flux:text class="mt-1">{{ now()->format('l, F j, Y') }}</flux:text>
            </div>
            <flux:button href="{{ route('sites.index') }}" variant="subtle" size="sm">View all sites</flux:button>
        </div>

        {{-- Top stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Sites</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $totalSites }}</p>
                <p class="mt-0.5 text-xs text-emerald-500">All online</p>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Uptime</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ number_format($uptimePercent, 1) }}<span class="text-sm text-zinc-400">%</span></p>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Pages</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $totalPages }}</p>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Messages</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-zinc-100">{{ $unreadMessages }}</p>
                @if ($unreadMessages > 0)
                    <p class="mt-0.5 text-xs text-blue-400">Unread</p>
                @endif
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Errors</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums {{ $errorCount > 0 ? 'text-red-500' : 'text-zinc-900 dark:text-zinc-100' }}">{{ $errorCount }}</p>
                @if ($errorCount > 0)
                    <p class="mt-0.5 text-xs text-red-400">Needs attention</p>
                @endif
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-4 py-3">
                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">SEO Issues</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums {{ $seoIssueCount > 0 ? 'text-amber-500' : 'text-zinc-900 dark:text-zinc-100' }}">{{ $seoIssueCount }}</p>
                @if ($seoIssueCount > 0)
                    <p class="mt-0.5 text-xs text-amber-400">Needs attention</p>
                @endif
            </div>
        </div>

        {{-- Action needed + Recent activity --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @livewire('dashboard.alerts-panel')
            @livewire('dashboard.activity-feed')
        </div>

        {{-- Site health table --}}
        @livewire('dashboard.site-health-table')

        {{-- Bottom row: SEO + Upcoming + Expenses --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @livewire('dashboard.seo-issues-panel')
            @livewire('dashboard.upcoming-panel')
            @livewire('dashboard.expenses-panel')
        </div>
    </div>
</x-layouts.app>
