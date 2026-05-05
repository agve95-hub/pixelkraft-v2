<?php

namespace Tests\Feature;

use App\Http\Middleware\RememberExpandedSite;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RememberExpandedSiteTest extends TestCase
{
    use RefreshDatabase;

    private RememberExpandedSite $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RememberExpandedSite;
    }

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'res-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'res-'.uniqid(),
            'repo_url' => 'https://github.com/example/res',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function requestWithSite(Site $site): Request
    {
        $request = Request::create('/test');
        $route = new Route('GET', '/test', []);
        $route->bind($request);
        $route->setParameter('site', $site);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function requestWithoutSite(): Request
    {
        $request = Request::create('/test');
        $route = new Route('GET', '/test', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function passThrough(): \Closure
    {
        return fn ($r) => new Response('ok');
    }

    public function test_stores_site_id_in_session_when_site_param_present(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $request = $this->requestWithSite($site);
        $this->middleware->handle($request, $this->passThrough());

        $this->assertSame($site->id, session('expanded_site_id'));
    }

    public function test_does_not_set_session_when_no_site_param(): void
    {
        $request = $this->requestWithoutSite();
        $this->middleware->handle($request, $this->passThrough());

        $this->assertNull(session('expanded_site_id'));
    }

    public function test_passes_request_through_unchanged(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $request = $this->requestWithSite($site);
        $response = $this->middleware->handle($request, $this->passThrough());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_overwrites_previous_session_value(): void
    {
        $user = $this->makeUser();
        $site1 = $this->makeSite($user);
        $site2 = $this->makeSite($user);

        session(['expanded_site_id' => $site1->id]);

        $request = $this->requestWithSite($site2);
        $this->middleware->handle($request, $this->passThrough());

        $this->assertSame($site2->id, session('expanded_site_id'));
    }
}
