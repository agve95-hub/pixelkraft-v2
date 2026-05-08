<?php

namespace Tests\Unit;

use App\Enums\BlogPostStatus;
use App\Enums\DeployStatus;
use App\Models\BlogPost;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeployStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    // ── DeployStatus enum ─────────────────────────

    public function test_queued_can_transition_to_building(): void
    {
        $this->assertTrue(DeployStatus::Queued->canTransitionTo(DeployStatus::Building));
    }

    public function test_building_can_transition_to_deploying(): void
    {
        $this->assertTrue(DeployStatus::Building->canTransitionTo(DeployStatus::Deploying));
    }

    public function test_deploying_can_transition_to_live(): void
    {
        $this->assertTrue(DeployStatus::Deploying->canTransitionTo(DeployStatus::Live));
    }

    public function test_live_can_transition_to_queued_for_redeploy(): void
    {
        $this->assertTrue(DeployStatus::Live->canTransitionTo(DeployStatus::Queued));
    }

    public function test_live_can_transition_to_deploying_for_rollback(): void
    {
        $this->assertTrue(DeployStatus::Live->canTransitionTo(DeployStatus::Deploying));
    }

    public function test_active_pipeline_states_allow_transition_to_failed(): void
    {
        $activePipelineStates = [
            DeployStatus::Queued,
            DeployStatus::Cloning,
            DeployStatus::Parsing,
            DeployStatus::Building,
            DeployStatus::Deploying,
            DeployStatus::Live,
        ];

        foreach ($activePipelineStates as $status) {
            $this->assertTrue(
                $status->canTransitionTo(DeployStatus::Failed),
                "Expected [{$status->value}] to allow → failed",
            );
        }

        // Draft and Idle use direct update() for failure — not modelled in the enum
        $this->assertFalse(DeployStatus::Draft->canTransitionTo(DeployStatus::Failed));
        $this->assertFalse(DeployStatus::Idle->canTransitionTo(DeployStatus::Failed));
    }

    public function test_active_pipeline_states_are_marked_active(): void
    {
        foreach ([DeployStatus::Queued, DeployStatus::Cloning, DeployStatus::Parsing, DeployStatus::Building, DeployStatus::Deploying] as $status) {
            $this->assertTrue($status->isActive(), "Expected [{$status->value}] to be active");
        }

        foreach ([DeployStatus::Draft, DeployStatus::Idle, DeployStatus::Live, DeployStatus::Failed] as $status) {
            $this->assertFalse($status->isActive(), "Expected [{$status->value}] to be inactive");
        }
    }

    public function test_building_cannot_skip_to_live(): void
    {
        $this->assertFalse(DeployStatus::Building->canTransitionTo(DeployStatus::Live));
    }

    public function test_draft_cannot_jump_directly_to_live(): void
    {
        $this->assertFalse(DeployStatus::Draft->canTransitionTo(DeployStatus::Live));
    }

    // ── Site::transitionDeployStatus ──────────────

    private function makeSite(DeployStatus $status): Site
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'u'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        return Site::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'slug' => 'test-'.uniqid(),
            'repo_url' => 'https://github.com/example/test',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => $status,
        ]);
    }

    public function test_site_transitions_deploy_status_successfully(): void
    {
        $site = $this->makeSite(DeployStatus::Queued);
        $site->transitionDeployStatus(DeployStatus::Building);
        $this->assertSame(DeployStatus::Building, $site->fresh()->deploy_status);
    }

    public function test_site_throws_on_invalid_transition(): void
    {
        $site = $this->makeSite(DeployStatus::Building);
        $this->expectException(\LogicException::class);
        $site->transitionDeployStatus(DeployStatus::Live); // must go through Deploying first
    }

    // ── BlogPostStatus enum ───────────────────────

    public function test_draft_can_transition_to_published(): void
    {
        $this->assertTrue(BlogPostStatus::Draft->canTransitionTo(BlogPostStatus::Published));
    }

    public function test_draft_can_transition_to_scheduled(): void
    {
        $this->assertTrue(BlogPostStatus::Draft->canTransitionTo(BlogPostStatus::Scheduled));
    }

    public function test_published_can_transition_back_to_draft(): void
    {
        $this->assertTrue(BlogPostStatus::Published->canTransitionTo(BlogPostStatus::Draft));
    }

    public function test_published_cannot_transition_to_scheduled(): void
    {
        $this->assertFalse(BlogPostStatus::Published->canTransitionTo(BlogPostStatus::Scheduled));
    }

    // ── BlogPost::transitionStatus ────────────────

    public function test_blog_post_transitions_status_successfully(): void
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'bp'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Blog',
            'slug' => 'blog-'.uniqid(),
            'repo_url' => 'https://github.com/example/blog',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Hello',
            'slug' => 'hello',
            'status' => BlogPostStatus::Draft,
        ]);

        $post->transitionStatus(BlogPostStatus::Published);
        $this->assertSame(BlogPostStatus::Published, $post->fresh()->status);
    }

    public function test_blog_post_throws_on_invalid_transition(): void
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'bpx'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Blog',
            'slug' => 'blogx-'.uniqid(),
            'repo_url' => 'https://github.com/example/blog',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        $post = BlogPost::create([
            'site_id' => $site->id,
            'title' => 'Hello',
            'slug' => 'hellox',
            'status' => BlogPostStatus::Published,
        ]);

        $this->expectException(\LogicException::class);
        $post->transitionStatus(BlogPostStatus::Scheduled); // Published cannot go to Scheduled
    }
}
