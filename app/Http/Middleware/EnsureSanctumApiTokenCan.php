<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSanctumApiTokenCan
{
    /**
     * Require a Sanctum personal access token to carry the ability for the
     * current route. Cookie/session authentication (SPA) is unchanged.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $token = $user->currentAccessToken();

        if ($token === null) {
            return $next($request);
        }

        $ability = $this->requiredAbilityForRoute($request);

        if ($ability === null) {
            abort(403, 'This API route is not available for token authentication.');
        }

        if (! $user->tokenCan($ability)) {
            abort(403, 'This token is not allowed to perform this action.');
        }

        return $next($request);
    }

    private function requiredAbilityForRoute(Request $request): ?string
    {
        $route = $request->route();
        if ($route === null) {
            return null;
        }

        $name = $route->getName();

        return match ($name) {
            'api.v1.sites.index',
            'api.v1.sites.show',
            'api.v1.sites.pages',
            'api.v1.sites.deploys',
            'api.v1.sites.analytics',
            'api.v1.sites.releases',
            'api.v1.sites.git-operations' => 'pixelkraft:sites:read',
            'api.v1.sites.sync' => 'pixelkraft:sites:sync',
            'api.v1.sites.deploy' => 'pixelkraft:sites:deploy',
            'api.v1.sites.rollback' => 'pixelkraft:sites:rollback',
            'api.v1.notifications.index' => 'pixelkraft:notifications:read',
            'api.v1.notifications.read',
            'api.v1.notifications.readAll' => 'pixelkraft:notifications:write',
            default => null,
        };
    }
}
