<?php

namespace App\Providers;

use App\Models\BlogPost;
use App\Models\Invoice;
use App\Models\Page;
use App\Models\Site;
use App\Policies\BlogPostPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PagePolicy;
use App\Policies\SitePolicy;
use App\Support\SchemaState;
use App\Support\SiteAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Provide a safe fallback so csp_nonce() never throws outside the
        // middleware context (e.g. during queue workers or Artisan commands).
        $this->app->bindIf('csp-nonce', fn () => '');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Authorization policies ───────────────────────────────────────────
        // These provide object-level authorization as a second layer of defence
        // behind the EnsureSiteAccess middleware / SiteAccess::query() tenancy scope.
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(BlogPost::class, BlogPostPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        // ── Layout view composer ─────────────────────────────────────────────
        // Injects sidebar navigation and search index data into the app shell
        // so the layout view itself stays free of DB queries.
        View::composer('components.layouts.app', function ($view) {
            $navSitesQuery = SiteAccess::query()
                ->select('id', 'name', 'slug', 'deploy_status')
                ->orderBy('name');

            if (SchemaState::hasColumn('sites', 'maintenance_settings')) {
                $navSitesQuery->addSelect('maintenance_settings');
            }

            $withCounts = [];

            if (SchemaState::hasTable('site_inbox_messages')) {
                $withCounts['inboxMessages as unread_inbox_count'] = fn ($q) => $q
                    ->where('direction', 'inbound')
                    ->where('is_read', false);
            }

            if (SchemaState::hasTable('invoices')) {
                $withCounts['invoices as unpaid_invoices_count'] = fn ($q) => $q
                    ->where('status', 'unpaid');
            }

            if (SchemaState::hasTable('reminders')) {
                $withCounts['reminders as overdue_reminders_count'] = fn ($q) => $q
                    ->where('is_done', false)
                    ->whereDate('due_date', '<', now()->toDateString());
            }

            if ($withCounts !== []) {
                $navSitesQuery->withCount($withCounts);
            }

            $navSites = $navSitesQuery->get();

            $searchIndex = collect([
                ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'All sites', 'href' => route('sites.index')],
                ['label' => 'New project', 'href' => route('sites.create')],
                ['label' => 'Settings', 'href' => route('settings')],
                ['label' => 'Analytics (all sites)', 'href' => route('analytics')],
                ['label' => 'Inbox (accounts)', 'href' => route('inbox')],
                ['label' => 'Subscribers', 'href' => route('subscribers')],
                ['label' => 'Newsletters', 'href' => route('newsletters')],
            ]);

            /** @var Collection<int, Site> $navSites */
            foreach ($navSites as $s) {
                $searchIndex->push(['label' => $s->name.' — Overview', 'href' => route('sites.show', $s)]);
                $searchIndex->push(['label' => $s->name.' — Pages', 'href' => route('sites.pages', $s)]);
                $searchIndex->push(['label' => $s->name.' — Inbox', 'href' => route('sites.inbox', $s)]);
                $searchIndex->push(['label' => $s->name.' — Reports', 'href' => route('sites.reports', $s)]);
                $searchIndex->push(['label' => $s->name.' — Campaigns', 'href' => route('sites.campaigns', $s)]);
                $searchIndex->push(['label' => $s->name.' — Expenses', 'href' => route('sites.expenses', $s)]);
                $searchIndex->push(['label' => $s->name.' — Invoices', 'href' => route('sites.invoices', $s)]);
                $searchIndex->push(['label' => $s->name.' — Reminders', 'href' => route('sites.reminders', $s)]);
                $searchIndex->push(['label' => $s->name.' — Analytics', 'href' => route('sites.analytics', $s)]);
                $searchIndex->push(['label' => $s->name.' — Maintenance', 'href' => route('sites.maintenance', $s)]);
                $searchIndex->push(['label' => $s->name.' — Media', 'href' => route('sites.files', $s)]);
            }

            $view->with(compact('navSites', 'searchIndex'));
        });

        // ── Blade directives ─────────────────────────────────────────────────
        // @cspNonce — attach a CSP nonce attribute to a <script> or <style> tag.
        // Usage:  <script @cspNonce>…</script>
        Blade::directive('cspNonce', fn () => '<?php echo \'nonce="\' . csp_nonce() . \'"\'; ?>');
    }
}
