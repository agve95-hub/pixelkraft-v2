<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Jobs\DeploySiteJob;
use App\Jobs\ParseSiteJob;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SiteCreateWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_path_source_type_is_rejected(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Wizard User',
            'email' => 'wizard@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $this->actingAs($user);

        $this->postJson('/dashboard/sites', [
            'name' => 'Mounted Site',
            'project_type' => 'static_html',
            'source_type' => 'server_path',
            'server_path' => public_path(),
            'branch' => 'main',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['source_type']);

        Queue::assertNothingPushed();
    }

    public function test_github_site_creation_dispatches_real_deploy_job(): void
    {
        Queue::fake();

        $user = User::create([
            'name' => 'Wizard User',
            'email' => 'wizard-3@example.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/dashboard/sites', [
            'name' => 'GitHub Site',
            'project_type' => 'react',
            'source_type' => 'github',
            'repo_url' => 'https://github.com/example/site',
            'branch' => 'main',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['siteId']);

        $site = Site::query()->findOrFail($response->json('siteId'));

        $this->assertSame(DeployStatus::Queued, $site->deploy_status);

        Queue::assertPushed(DeploySiteJob::class, function (DeploySiteJob $job) use ($site) {
            return $job->site->is($site) && $job->triggeredBy === 'wizard';
        });

        Queue::assertNotPushed(ParseSiteJob::class);
    }
}
