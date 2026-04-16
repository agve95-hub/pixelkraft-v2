<?php

namespace Tests\Feature;

use App\Jobs\DeploySiteJob;
use App\Models\BlogPost;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublishScheduledCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_scheduled_post_when_due_without_repo(): void
    {
        Bus::fake([DeploySiteJob::class]);

        $user = User::create([
            'name' => 'Writer',
            'email' => 'writer@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Blog Site',
            'slug' => 'blog-site',
            'repo_url' => 'https://github.com/acme/blog-site.git',
            'branch' => 'main',
            'repo_path' => storage_path('framework/testing/no-repo-'.uniqid('', true)),
        ]);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Future Post',
            'slug' => 'future-post',
            'body' => 'Hello world',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->artisan('pixelkraft:publish-scheduled')->assertSuccessful();

        $post->refresh();
        $status = $post->status instanceof \BackedEnum ? $post->status->value : $post->status;
        $this->assertSame('published', $status);
        $this->assertNotNull($post->published_at);
    }

    public function test_command_noops_when_nothing_due(): void
    {
        $this->artisan('pixelkraft:publish-scheduled')->assertSuccessful();
    }
}
