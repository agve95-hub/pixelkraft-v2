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
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
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

        // ── Blade directives ─────────────────────────────────────────────────
        // @cspNonce — attach a CSP nonce attribute to a <script> or <style> tag.
        // Usage:  <script @cspNonce>…</script>
        Blade::directive('cspNonce', fn () => '<?php echo \'nonce="\' . csp_nonce() . \'"\'; ?>');
    }
}
