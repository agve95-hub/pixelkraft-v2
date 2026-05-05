<?php

use App\Models\Expense;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/sites/{site}/expenses', fn (Site $site) => view('dashboard.sites.expenses', ['site' => $site]))->name('sites.expenses');
Route::post('/sites/{site}/expenses', function (Request $request, Site $site) {
    $d = $request->validate(['label' => 'required|string|max:255', 'amount' => 'required|numeric|min:0.01', 'currency' => 'required|string|size:3', 'expense_date' => 'required|date']);
    $site->expenses()->create($d);

    return back();
})->name('sites.expenses.store');
Route::put('/sites/{site}/expenses/{expense}', function (Request $request, Site $site, Expense $expense) {
    abort_unless($expense->site_id === $site->id, 403);
    $d = $request->validate(['label' => 'required|string|max:255', 'amount' => 'required|numeric|min:0.01', 'currency' => 'required|string|size:3', 'expense_date' => 'required|date']);
    $expense->update($d);

    return back();
})->name('sites.expenses.update');
Route::delete('/sites/{site}/expenses/{expense}', function (Site $site, Expense $expense) {
    abort_unless($expense->site_id === $site->id, 403);
    $expense->delete();

    return back();
})->name('sites.expenses.destroy');
Route::delete('/sites/{site}/expenses', function (Request $request, Site $site) {
    $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'string'])['ids'];
    $site->expenses()->whereIn('id', $ids)->delete();

    return back();
})->name('sites.expenses.bulk-destroy');
