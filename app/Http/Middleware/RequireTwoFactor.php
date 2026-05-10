<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect admin users to the 2FA setup screen until they have confirmed
 * a TOTP device.  Editor-role users are not affected.
 *
 * This middleware must run after authentication (i.e. inside the 'auth' or
 * 'web' middleware group).  Register it in bootstrap/app.php under the web
 * group so it applies to all dashboard routes automatically.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow the enforcement to be disabled via config for local/test environments.
        // In production, set ENFORCE_2FA=true (or leave unset — defaults to true in production).
        if (! config('platform.enforce_two_factor', app()->isProduction())) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return $next($request);
        }

        // Allow the user through if 2FA is already confirmed for this session.
        if ($user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        // Let the 2FA setup and challenge routes through to avoid redirect loops.
        if ($request->routeIs(
            'two-factor.*',
            'login',
            'logout',
            'password.*',
            'settings',          // where 2FA setup lives — must not redirect here or loop
            'system.*',
        )) {
            return $next($request);
        }

        // Admin has not enabled 2FA — redirect to the profile / 2FA setup page.
        return redirect()->route('settings')
            ->with('status', 'two-factor-required');
    }
}
