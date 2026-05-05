<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSiteAccess;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnsureSiteAccessMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private EnsureSiteAccess $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureSiteAccess;
    }

    private function makeUser(string $role = 'editor'): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'esa-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => $role,
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'esa-'.uniqid(),
            'repo_url' => 'https://github.com/example/esa',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function buildRequest(?User $user, mixed $siteParam): Request
    {
        $request = Request::create('/test');

        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        $route = new Route('GET', '/test', []);
        $route->bind($request);

        if ($siteParam !== null) {
            $route->setParameter('site', $siteParam);
        }

        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function passThrough(): \Closure
    {
        return fn ($r) => new Response('ok');
    }

    // ── no site param ────────────────────────────

    public function test_passes_through_when_no_site_param(): void
    {
        $request = $this->buildRequest(null, null);
        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ── unauthenticated ──────────────────────────

    public function test_aborts_401_when_unauthenticated_and_site_param_present(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $request = $this->buildRequest(null, $site->id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        try {
            $this->middleware->handle($request, $this->passThrough());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
            throw $e;
        }
    }

    // ── site not visible to user ─────────────────

    public function test_aborts_404_when_site_not_visible_to_user(): void
    {
        $owner = $this->makeUser();
        $site = $this->makeSite($owner);

        $otherUser = $this->makeUser();

        $request = $this->buildRequest($otherUser, $site->id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        try {
            $this->middleware->handle($request, $this->passThrough());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            throw $e;
        }
    }

    // ── site visible to user ─────────────────────

    public function test_passes_through_when_user_owns_site(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $request = $this->buildRequest($user, $site->id);
        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_passes_through_when_admin_accessing_any_site(): void
    {
        $owner = $this->makeUser();
        $site = $this->makeSite($owner);

        $admin = $this->makeUser('admin');
        $request = $this->buildRequest($admin, $site->id);
        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ── Livewire bypass ──────────────────────────

    public function test_passes_through_livewire_update_requests(): void
    {
        $request = Request::create('/test', 'POST');
        $request->headers->set('X-Livewire', 'true');

        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertSame(200, $response->getStatusCode());
    }

    // ── site model vs id param ───────────────────

    public function test_accepts_site_model_instance_as_route_param(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        // Pass the Site model directly (already resolved route-model binding)
        $request = $this->buildRequest($user, $site);
        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertSame(200, $response->getStatusCode());
    }
}
