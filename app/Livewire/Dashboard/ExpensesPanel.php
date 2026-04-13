<?php

namespace App\Livewire\Dashboard;

use App\Models\Expense;
use App\Support\SiteAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ExpensesPanel extends Component
{
    public function render(): View
    {
        $visibleSiteIds = SiteAccess::query()->pluck('id');

        $totalsBySite = Expense::query()
            ->whereIn('site_id', $visibleSiteIds)
            ->selectRaw('site_id, SUM(amount) as total, COUNT(*) as entry_count')
            ->groupBy('site_id')
            ->get()
            ->keyBy('site_id');

        $sites = SiteAccess::query()->orderBy('name')->get();

        $siteExpenses = $sites->map(function ($site) use ($totalsBySite) {
            $row = $totalsBySite->get($site->id);

            return [
                'site' => $site,
                'items' => (int) ($row->entry_count ?? 0),
                'total' => $row ? (float) $row->total : 0.0,
            ];
        })->filter(fn (array $row) => $row['items'] > 0)->values();

        $grandTotal = (float) $siteExpenses->sum('total');

        return view('livewire.dashboard.expenses-panel', [
            'siteExpenses' => $siteExpenses,
            'grandTotal' => $grandTotal,
        ]);
    }
}
