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
        // middleware context (for example during queue workers or Artisan commands).
        $this->app->bindIf('csp-nonce', fn () => '');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Authorization policies
        // These provide object-level authorization as a second layer of defence
        // behind the EnsureSiteAccess middleware / SiteAccess::query() tenancy scope.
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(BlogPost::class, BlogPostPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

        // Layout view composer
        // Inject sidebar navigation and search index data into the app shell so the
        // layout view itself stays free of DB queries.
        View::composer('components.layouts.app', function ($view) {
            $navSitesQuery = SiteAccess::query()
                ->select('id', 'name', 'slug', 'deploy_status')
                ->orderBy('name');

            if (SchemaState::hasColumn('sites', 'maintenance_settings')) {
                $navSitesQuery->addSelect('maintenance_settings');
            }

            $withCounts = [];

            if (SchemaState::hasTable('site_inbox_messages')) {
                $withCounts['inboxMessages as unread_inbox_count'] = fn ($query) => $query
                    ->where('direction', 'inbound')
                    ->where('is_read', false);
            }

            if (SchemaState::hasTable('invoices')) {
                $withCounts['invoices as unpaid_invoices_count'] = fn ($query) => $query
                    ->where('status', 'unpaid');
            }

            if (SchemaState::hasTable('reminders')) {
                $withCounts['reminders as overdue_reminders_count'] = fn ($query) => $query
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
            foreach ($navSites as $site) {
                $searchIndex->push(['label' => $site->name.' - Overview', 'href' => route('sites.show', $site)]);
                $searchIndex->push(['label' => $site->name.' - Pages & SEO', 'href' => route('sites.pages', $site)]);
                $searchIndex->push(['label' => $site->name.' - Redirects', 'href' => route('seo.redirects', $site)]);
                $searchIndex->push(['label' => $site->name.' - Blog', 'href' => route('blog.index', $site)]);
                $searchIndex->push(['label' => $site->name.' - Products', 'href' => route('products.index', $site)]);
                $searchIndex->push(['label' => $site->name.' - Templates', 'href' => route('templates.index', $site)]);
                $searchIndex->push(['label' => $site->name.' - Subscribers', 'href' => route('sites.subscribers', $site)]);
                $searchIndex->push(['label' => $site->name.' - Inbox', 'href' => route('sites.inbox', $site)]);
                $searchIndex->push(['label' => $site->name.' - Reports', 'href' => route('sites.reports', $site)]);
                $searchIndex->push(['label' => $site->name.' - Campaigns', 'href' => route('sites.campaigns', $site)]);
                $searchIndex->push(['label' => $site->name.' - Expenses', 'href' => route('sites.expenses', $site)]);
                $searchIndex->push(['label' => $site->name.' - Invoices', 'href' => route('sites.invoices', $site)]);
                $searchIndex->push(['label' => $site->name.' - Reminders', 'href' => route('sites.reminders', $site)]);
                $searchIndex->push(['label' => $site->name.' - Analytics', 'href' => route('sites.analytics', $site)]);
                $searchIndex->push(['label' => $site->name.' - Maintenance', 'href' => route('sites.maintenance', $site)]);
                $searchIndex->push(['label' => $site->name.' - Media', 'href' => route('sites.files', $site)]);
                $searchIndex->push(['label' => $site->name.' - Site settings', 'href' => route('sites.settings', $site)]);
            }

            $view->with(compact('navSites', 'searchIndex'));
        });

        // Blade directives
        // @cspNonce attaches a CSP nonce attribute to a <script> or <style> tag.
        // Usage: <script @cspNonce></script>
        Blade::directive('cspNonce', fn () => '<?php echo \'nonce="\' . csp_nonce() . \'"\'; ?>');
    }
}
