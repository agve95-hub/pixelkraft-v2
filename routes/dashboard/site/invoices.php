<?php

use App\Http\Controllers\InvoicePdfController;
use App\Models\Invoice;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;

Route::get('/sites/{site}/invoices', fn (Site $site) => view('dashboard.sites.invoices', ['site' => $site]))->name('sites.invoices');
Route::post('/sites/{site}/invoices', function (Request $request, Site $site) {
    $d = $request->validate(['number' => 'nullable|string|max:100', 'invoice_date' => 'required|date', 'due_date' => 'nullable|date', 'currency_code' => 'required|string|size:3', 'bill_to' => 'nullable|string|max:1000', 'from_address' => 'nullable|string|max:1000', 'tax_rate' => 'nullable|numeric|min:0|max:100', 'discount_percent' => 'nullable|numeric|min:0|max:100', 'notes' => 'nullable|string', 'payment_terms' => 'nullable|string|max:500', 'payment_details' => 'nullable|string|max:2000', 'items' => 'nullable|array', 'items.*.description' => 'required|string|max:500', 'items.*.quantity' => 'required|numeric|min:0', 'items.*.rate' => 'required|numeric']);
    $invoice = $site->invoices()->create(array_merge(Arr::except($d, ['items']), ['number' => $d['number'] ?? Invoice::nextNumberForSite($site), 'status' => 'unpaid', 'tax_rate' => $d['tax_rate'] ?? 0, 'discount_percent' => $d['discount_percent'] ?? 0, 'payment_terms' => $d['payment_terms'] ?? 'net30']));
    foreach ($d['items'] ?? [] as $i => $item) {
        $invoice->items()->create(['description' => $item['description'], 'quantity' => $item['quantity'], 'rate' => $item['rate'], 'sort_order' => $i]);
    }

    return back()->with('success', 'Invoice created.');
})->name('sites.invoices.store');
Route::put('/sites/{site}/invoices/{invoice}', function (Request $request, Site $site, Invoice $invoice) {
    abort_unless($invoice->site_id === $site->id, 403);
    $d = $request->validate(['invoice_date' => 'required|date', 'due_date' => 'nullable|date', 'currency_code' => 'required|string|size:3', 'bill_to' => 'nullable|string|max:1000', 'from_address' => 'nullable|string|max:1000', 'tax_rate' => 'nullable|numeric|min:0|max:100', 'discount_percent' => 'nullable|numeric|min:0|max:100', 'notes' => 'nullable|string', 'payment_terms' => 'nullable|string|max:500', 'payment_details' => 'nullable|string|max:2000', 'items' => 'nullable|array', 'items.*.description' => 'required|string|max:500', 'items.*.quantity' => 'required|numeric|min:0', 'items.*.rate' => 'required|numeric']);
    $invoice->update(Arr::except($d, ['items']));
    $invoice->items()->delete();
    foreach ($d['items'] ?? [] as $i => $item) {
        $invoice->items()->create(['description' => $item['description'], 'quantity' => $item['quantity'], 'rate' => $item['rate'], 'sort_order' => $i]);
    }

    return back()->with('success', 'Invoice updated.');
})->name('sites.invoices.update');
Route::post('/sites/{site}/invoices/{invoice}/mark-paid', function (Site $site, Invoice $invoice) {
    abort_unless($invoice->site_id === $site->id, 403);
    $invoice->update(['status' => 'paid', 'paid_at' => now()]);

    return back();
})->name('sites.invoices.mark-paid');
Route::post('/sites/{site}/invoices/{invoice}/duplicate', function (Site $site, Invoice $invoice) {
    abort_unless($invoice->site_id === $site->id, 403);
    $copy = $invoice->replicate();
    $copy->number = Invoice::nextNumberForSite($site);
    $copy->status = 'unpaid';
    $copy->paid_at = null;
    $copy->invoice_date = now()->toDateString();
    $copy->due_date = now()->addDays(30)->toDateString();
    $copy->save();
    foreach ($invoice->items as $item) {
        $newItem = $item->replicate();
        $newItem->invoice_id = $copy->id;
        $newItem->save();
    }

    return back();
})->name('sites.invoices.duplicate');
Route::delete('/sites/{site}/invoices/{invoice}', function (Site $site, Invoice $invoice) {
    abort_unless($invoice->site_id === $site->id, 403);
    $invoice->delete();

    return back();
})->name('sites.invoices.destroy');
Route::get('/sites/{site}/invoices/{invoice}/pdf', InvoicePdfController::class)->name('sites.invoices.pdf');
