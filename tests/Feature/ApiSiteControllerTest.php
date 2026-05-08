<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Jobs\DeploySiteJob;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSiteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sites_index_returns_only_visible_sites(): void
    {
        $alice = User::create([
            'name' => 'Alice',
            'email' => 'alice-api@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $bob = User::create([
            'name' => 'Bob',
            'email' => 'bob-api@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        Site::create([
            'user_id' => $alice->id,
            'name' => 'Alice API Project',
            'slug' => 'alice-api-proj',
            'repo_url' => 'https://github.com/example/aap.git',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Site::create([
            'user_id' => $bob->id,
            'name' => 'Bob Hidden',
            'slug' => 'bob-hidden-api',
            'repo_url' => 'https://github.com/example/bh.git',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Sanctum::actingAs($alice, ['*']);

        $response = $this->getJson('/api/v1/sites');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Alice API Project', $data[0]['name'] ?? null);
    }

    public function test_sites_show_returns_site_json(): void
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner-api@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Show Me',
            'slug' => 'show-me-api',
            'repo_url' => 'https://github.com/example/sm.git',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson("/api/v1/sites/{$site->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Show Me')
            ->assertJsonPath('data.slug', 'show-me-api');
    }

    public function test_api_deploy_queues_site_before_dispatching_job(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Deploy Owner',
            'email' => 'deploy-api@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'API Deploy',
            'slug' => 'api-deploy',
            'repo_url' => 'https://github.com/example/api-deploy.git',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => DeployStatus::Live,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/v1/sites/{$site->id}/deploy")
            ->assertOk()
            ->assertJsonPath('status', 'dispatched');

        Queue::assertPushed(DeploySiteJob::class);
        $this->assertSame(DeployStatus::Queued, $site->fresh()->deploy_status);
    }

    public function test_api_deploy_rejects_duplicate_active_deploy(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Deploy Owner',
            'email' => 'deploy-api-queued@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'API Deploy Queued',
            'slug' => 'api-deploy-queued',
            'repo_url' => 'https://github.com/example/api-deploy-queued.git',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => DeployStatus::Queued,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson("/api/v1/sites/{$site->id}/deploy")
            ->assertConflict()
            ->assertJsonPath('error', 'conflict');

        Queue::assertNothingPushed();
    }
}
