<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunLighthouseCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'rlh-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'My Site',
            'slug' => 'rlh-'.uniqid(),
            'repo_url' => 'https://github.com/example/rlh',
            'branch' => 'main',
            'project_type' => 'static_html',
            'is_active' => true,
            'deploy_status' => DeployStatus::Live,
            'domain' => 'example.com',
        ], $attrs));
    }

    private function makePage(Site $site): Page
    {
        return Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
            'is_published' => true,
        ]);
    }

    private function psiResponse(int $perf = 85, int $a11y = 90, int $bp = 80, int $seo = 95): array
    {
        return [
            'lighthouseResult' => [
                'categories' => [
                    'performance' => ['score' => $perf / 100],
                    'accessibility' => ['score' => $a11y / 100],
                    'best-practices' => ['score' => $bp / 100],
                    'seo' => ['score' => $seo / 100],
                ],
            ],
        ];
    }

    public function test_scores_published_pages_and_stores_results(): void
    {
        Http::fake([
            'https://www.googleapis.com/*' => Http::response($this->psiResponse(90, 95, 85, 100), 200),
        ]);

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        $this->artisan('platform:run-lighthouse')->assertSuccessful();

        $page->refresh();
        $this->assertNotNull($page->lighthouse_score);
        $this->assertSame(90, $page->lighthouse_score['performance']);
        $this->assertSame(95, $page->lighthouse_score['accessibility']);
        $this->assertSame(85, $page->lighthouse_score['best_practices']);
        $this->assertSame(100, $page->lighthouse_score['seo']);
    }

    public function test_skips_inactive_sites(): void
    {
        Http::fake();

        $user = $this->makeUser();
        $this->makeSite($user, ['is_active' => false]);

        $this->artisan('platform:run-lighthouse')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_skips_sites_without_domain(): void
    {
        Http::fake();

        $user = $this->makeUser();
        $this->makeSite($user, ['domain' => null]);

        $this->artisan('platform:run-lighthouse')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_skips_sites_not_live(): void
    {
        Http::fake();

        $user = $this->makeUser();
        $this->makeSite($user, ['deploy_status' => DeployStatus::Idle]);

        $this->artisan('platform:run-lighthouse')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_filters_by_site_slug_option(): void
    {
        Http::fake([
            'https://www.googleapis.com/*' => Http::response($this->psiResponse(), 200),
        ]);

        $user = $this->makeUser();
        $site1 = $this->makeSite($user, ['slug' => 'rlh-target']);
        $site2 = $this->makeSite($user, ['domain' => 'other.com']);

        $this->makePage($site1);
        $this->makePage($site2);

        $this->artisan('platform:run-lighthouse', ['--site' => 'rlh-target'])
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_handles_api_failure_gracefully(): void
    {
        Http::fake([
            'https://www.googleapis.com/*' => Http::response(['error' => 'quota exceeded'], 429),
        ]);

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);

        $this->artisan('platform:run-lighthouse')->assertSuccessful();

        // Score not stored on API failure
        $page->refresh();
        $this->assertNull($page->lighthouse_score);
    }

    public function test_accepts_desktop_strategy_option(): void
    {
        $captured = [];
        Http::fake([
            'https://www.googleapis.com/*' => function ($request) use (&$captured) {
                $captured[] = $request->url();

                return Http::response($this->psiResponse(), 200);
            },
        ]);

        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $this->makePage($site);

        $this->artisan('platform:run-lighthouse', ['--strategy' => 'desktop'])
            ->assertSuccessful();

        $this->assertNotEmpty($captured);
        $this->assertStringContainsString('strategy=desktop', $captured[0]);
    }
}
