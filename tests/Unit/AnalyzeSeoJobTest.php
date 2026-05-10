<?php

namespace Tests\Unit;

use App\Jobs\AnalyzeSeoJob;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\SeoAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AnalyzeSeoJobTest extends TestCase
{
    use RefreshDatabase;

    private function makePage(): Page
    {
        $user = User::create([
            'name' => 'U', 'email' => 'asj-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);
        $site = Site::create([
            'user_id' => $user->id, 'name' => 'S',
            'slug' => 'asj-'.uniqid(), 'branch' => 'main', 'project_type' => 'static_html',
        ]);

        return Page::create([
            'site_id' => $site->id,
            'url_path' => '/test-page',
            'file_path' => 'index.html',
            'title' => 'Test Page',
        ]);
    }

    public function test_job_calls_analyzer_with_the_given_page(): void
    {
        $page = $this->makePage();

        $analyzer = Mockery::mock(SeoAnalyzer::class);
        $analyzer->shouldReceive('analyze')
            ->once()
            ->withArgs(fn ($p) => $p->id === $page->id);

        $this->app->instance(SeoAnalyzer::class, $analyzer);

        $job = new AnalyzeSeoJob($page->id);
        $job->handle($analyzer);
    }

    public function test_job_is_a_no_op_when_page_does_not_exist(): void
    {
        $analyzer = Mockery::mock(SeoAnalyzer::class);
        $analyzer->shouldNotReceive('analyze');

        $this->app->instance(SeoAnalyzer::class, $analyzer);

        $job = new AnalyzeSeoJob('non-existent-uuid');
        $job->handle($analyzer);

        $this->assertTrue(true); // reached without exception
    }

    public function test_job_has_unique_id_equal_to_page_id(): void
    {
        $page = $this->makePage();
        $job = new AnalyzeSeoJob($page->id);

        $this->assertSame($page->id, $job->uniqueId());
    }

    public function test_job_is_on_parsing_queue(): void
    {
        $page = $this->makePage();
        $job = new AnalyzeSeoJob($page->id);

        $this->assertSame('parsing', $job->queue);
    }
}
