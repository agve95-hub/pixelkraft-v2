<?php

namespace App\Livewire\Dashboard;

use App\Models\Site;
use Livewire\Component;

class ExpensesPanel extends Component
{
    public function render()
    {
        $sites = Site::query()->withCount('pages')->orderBy('name')->get();

        $siteExpenses = $sites->map(function (Site $site) {
            $baseCost = 4.99;
            $pageCost = max(0, $site->pages_count - 3) * 1.00;
            $sslCost = $site->ssl_status === 'active' ? 0 : 0;
            $total = $baseCost + $pageCost + $sslCost;

            return [
                'site' => $site,
                'items' => $site->pages_count,
                'total' => $total,
            ];
        });

        $grandTotal = $siteExpenses->sum('total');

        return view('livewire.dashboard.expenses-panel', [
            'siteExpenses' => $siteExpenses,
            'grandTotal' => $grandTotal,
        ]);
    }
}
