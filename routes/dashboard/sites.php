<?php

use Illuminate\Support\Facades\Route;

// ── Site-scoped routes (site.access + expand sidebar) ───────────────────────
// Each sub-file is isolated by concern. All routes here are prefixed under
// the auth + scopeBindings + /dashboard group from routes/web.php.

Route::middleware(['site.access', 'expand.site.sidebar'])->group(function () {
    require __DIR__.'/site/show.php';             // Site overview + inbox
    require __DIR__.'/site/invoices.php';          // Invoice CRUD + PDF
    require __DIR__.'/site/campaigns.php';         // Popup/announcement campaigns
    require __DIR__.'/site/expenses.php';          // Expense tracking
    require __DIR__.'/site/reminders-reports.php'; // Reminders + client reports
    require __DIR__.'/site/core.php';              // Settings, deploy, files, editor, SEO, maintenance
    require __DIR__.'/site/content.php';           // Blog, products, templates
    require __DIR__.'/site/email.php';             // Newsletter subscribers + campaigns
});
