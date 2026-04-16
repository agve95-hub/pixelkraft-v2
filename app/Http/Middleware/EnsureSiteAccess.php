<?php

namespace App\Http\Middleware;

use App\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteAccess
{
    /**
     * Ensure the authenticated user can access the route-bound site.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Livewire update requests don't carry route-model bindings; pass them through.
        if ($request->hasHeader('X-Livewire') || $request->routeIs('livewire.*')) {
            return $next($request);
        }

        $siteParam = $request->route('site');

        if ($siteParam === null) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $siteId = $siteParam instanceof Site ? $siteParam->id : (string) $siteParam;

        $site = Site::query()
            ->visibleTo($user)
            ->whereKey($siteId)
            ->first();

        if (! $site) {
            abort(404);
        }

        $request->route()->setParameter('site', $site);

        return $next($request);
    }
}
