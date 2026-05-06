<?php

namespace Tests\Unit;

use App\Jobs\SyncAnalyticsJob;
use App\Models\Site;
use App\Models\User;
use App\Services\AnalyticsAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SyncAnalyticsJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'saj-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'saj-'.uniqid(),
            'repo_url' => 'https://github.com/example/saj',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    public function test_calls_sync_all_when_no_site_given(): void
    {
        $aggregator = $this->mock(AnalyticsAggregator::class);
        $aggregator->shouldReceive('syncAll')->once();
        $aggregator->shouldReceive('syncSite')->never();

        $job = new SyncAnalyticsJob(null);
        $job->handle($aggregator);
    }

    public function test_calls_sync_site_when_site_given(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $aggregator = $this->mock(AnalyticsAggregator::class);
        $aggregator->shouldReceive('syncSite')->once()->with(\Mockery::on(fn ($s) => $s->id === $site->id));
        $aggregator->shouldReceive('syncAll')->never();

        $job = new SyncAnalyticsJob($site);
        $job->handle($aggregator);
    }

    public function test_tags_include_analytics(): void
    {
        $job = new SyncAnalyticsJob(null);

        $this->assertContains('analytics', $job->tags());
    }

    public function test_tags_include_site_id_when_site_given(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $job = new SyncAnalyticsJob($site);

        $this->assertContains("site:{$site->id}", $job->tags());
        $this->assertContains('analytics', $job->tags());
    }

    public function test_tags_do_not_include_site_when_null(): void
    {
        $job = new SyncAnalyticsJob(null);

        $tags = $job->tags();
        $this->assertSame(['analytics'], $tags);
    }

    public function test_job_uses_monitoring_queue(): void
    {
        $job = new SyncAnalyticsJob(null);

        $this->assertSame('monitoring', $job->queue);
    }
}
