<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use App\Services\BrokenLinkCrawler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrawlLinksCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'cl-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Crawl Site',
            'slug' => 'cl-'.uniqid(),
            'repo_url' => 'https://github.com/example/cl',
            'branch' => 'main',
            'project_type' => 'static_html',
            'is_active' => true,
            'domain' => 'example.com',
        ], $attrs));
    }

    private function crawlResult(int $total = 10, array $broken = [], array $redirects = []): array
    {
        return ['total_links' => $total, 'broken' => $broken, 'redirects' => $redirects];
    }

    public function test_crawls_active_sites_with_domain(): void
    {
        $user = $this->makeUser();
        $this->makeSite($user);

        $crawler = $this->mock(BrokenLinkCrawler::class);
        $crawler->shouldReceive('crawl')->once()->andReturn($this->crawlResult());

        $this->artisan('platform:crawl-links')->assertSuccessful();
    }

    public function test_skips_inactive_sites(): void
    {
        $user = $this->makeUser();
        $this->makeSite($user, ['is_active' => false]);

        $crawler = $this->mock(BrokenLinkCrawler::class);
        $crawler->shouldReceive('crawl')->never();

        $this->artisan('platform:crawl-links')->assertSuccessful();
    }

    public function test_skips_sites_without_domain(): void
    {
        $user = $this->makeUser();
        $this->makeSite($user, ['domain' => null]);

        $crawler = $this->mock(BrokenLinkCrawler::class);
        $crawler->shouldReceive('crawl')->never();

        $this->artisan('platform:crawl-links')->assertSuccessful();
    }

    public function test_filters_by_site_slug(): void
    {
        $user = $this->makeUser();
        $target = $this->makeSite($user, ['slug' => 'cl-target', 'domain' => 'target.com']);
        $this->makeSite($user, ['domain' => 'other.com']);

        $crawler = $this->mock(BrokenLinkCrawler::class);
        $crawler->shouldReceive('crawl')
            ->once()
            ->with(\Mockery::on(fn ($s) => $s->id === $target->id))
            ->andReturn($this->crawlResult());

        $this->artisan('platform:crawl-links', ['--site' => 'cl-target'])->assertSuccessful();
    }

    public function test_outputs_link_counts(): void
    {
        $user = $this->makeUser();
        $this->makeSite($user);

        $crawler = $this->mock(BrokenLinkCrawler::class);
        $crawler->shouldReceive('crawl')->andReturn($this->crawlResult(
            total: 25,
            broken: ['/bad', '/missing'],
            redirects: ['/old'],
        ));

        $this->artisan('platform:crawl-links')
            ->expectsOutputToContain('Links: 25')
            ->assertSuccessful();
    }

    public function test_crawls_multiple_sites(): void
    {
        $user = $this->makeUser();
        $this->makeSite($user, ['domain' => 'site1.com']);
        $this->makeSite($user, ['domain' => 'site2.com']);

        $crawler = $this->mock(BrokenLinkCrawler::class);
        $crawler->shouldReceive('crawl')->twice()->andReturn($this->crawlResult());

        $this->artisan('platform:crawl-links')->assertSuccessful();
    }
}
