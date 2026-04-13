<?php

namespace App\Livewire\Sites;

use App\Models\Expense;
use App\Support\SiteAccess;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseManager extends Component
{
    use WithPagination;

    public string $siteId;

    public string $form_label = '';

    public string $form_amount = '';

    public string $form_currency = 'EUR';

    public string $form_expense_date = '';

    public function mount(string $siteId): void
    {
        $this->siteId = $siteId;
        SiteAccess::findOrFail($this->siteId);
        $this->form_expense_date = now()->toDateString();
    }

    public function save(): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $validated = $this->validate([
            'form_label' => 'required|string|max:255',
            'form_amount' => 'required|numeric|min:0.01',
            'form_currency' => 'required|string|size:3',
            'form_expense_date' => 'required|date',
        ]);

        Expense::create([
            'site_id' => $site->id,
            'label' => $validated['form_label'],
            'amount' => $validated['form_amount'],
            'currency' => strtoupper($validated['form_currency']),
            'expense_date' => $validated['form_expense_date'],
        ]);

        $this->reset(['form_label', 'form_amount']);
        $this->form_currency = 'EUR';
        $this->form_expense_date = now()->toDateString();
        session()->flash('success', 'Expense recorded.');
        $this->resetPage();
    }

    public function delete(string $id): void
    {
        $site = SiteAccess::findOrFail($this->siteId);

        Expense::query()
            ->whereKey($id)
            ->where('site_id', $site->id)
            ->delete();

        session()->flash('success', 'Expense removed.');
        $this->resetPage();
    }

    public function render()
    {
        $site = SiteAccess::findOrFail($this->siteId);

        $expenses = Expense::query()
            ->where('site_id', $site->id)
            ->orderByDesc('expense_date')
            ->orderByDesc('created_at')
            ->paginate(15);

        $totals = Expense::query()
            ->where('site_id', $site->id)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->get();

        return view('livewire.sites.expense-manager', [
            'site' => $site,
            'expenses' => $expenses,
            'totalsByCurrency' => $totals,
        ]);
    }
}
