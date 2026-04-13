<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RememberExpandedSite
{
    /**
     * Remember which project’s sidebar subsection should stay open (mockup-style).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $site = $request->route('site');

        if ($site !== null) {
            session(['expanded_site_id' => $site->id]);
        }

        return $next($request);
    }
}
