<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireTwoFactor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RequireTwoFactorMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private RequireTwoFactor $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        // Enable enforcement for these tests so they exercise the real middleware logic.
        config()->set('platform.enforce_two_factor', true);
        $this->middleware = new RequireTwoFactor;
    }

    private function makeUser(string $role, bool $twoFactorConfirmed = false): User
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'rtf-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => $role,
        ]);

        if ($twoFactorConfirmed) {
            // two_factor_confirmed_at is not in $fillable (managed by Fortify);
            // use forceFill to bypass mass-assignment protection.
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        }

        return $user->fresh();
    }

    private function handle(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return $this->middleware->handle($request, fn ($r) => new Response('ok', 200));
    }

    private function requestFor(string $routeName, ?User $user = null): Request
    {
        $request = Request::create('/test');
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }
        $route = new Route('GET', '/test', []);
        $route->name($routeName);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    public function test_admin_without_2fa_is_redirected_to_settings(): void
    {
        $admin = $this->makeUser('admin', twoFactorConfirmed: false);
        $response = $this->handle($this->requestFor('dashboard', $admin));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/settings', $response->headers->get('Location') ?? '');
    }

    public function test_admin_with_confirmed_2fa_passes_through(): void
    {
        $admin = $this->makeUser('admin', twoFactorConfirmed: true);
        $response = $this->handle($this->requestFor('dashboard', $admin));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_editor_without_2fa_passes_through(): void
    {
        $editor = $this->makeUser('editor', twoFactorConfirmed: false);
        $response = $this->handle($this->requestFor('dashboard', $editor));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_unauthenticated_request_passes_through(): void
    {
        $response = $this->handle($this->requestFor('dashboard', user: null));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_admin_on_settings_route_passes_through_to_prevent_redirect_loop(): void
    {
        $admin = $this->makeUser('admin', twoFactorConfirmed: false);
        $response = $this->handle($this->requestFor('settings', $admin));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_admin_on_two_factor_route_passes_through(): void
    {
        $admin = $this->makeUser('admin', twoFactorConfirmed: false);
        $response = $this->handle($this->requestFor('two-factor.enable', $admin));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_admin_on_login_route_passes_through(): void
    {
        $admin = $this->makeUser('admin', twoFactorConfirmed: false);
        $response = $this->handle($this->requestFor('login', $admin));

        $this->assertSame(200, $response->getStatusCode());
    }
}
