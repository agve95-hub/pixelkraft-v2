<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Jobs\DeploySiteJob;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SiteDeployAndMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'deploy@example.com'): User
    {
        return User::create([
            'name' => 'Deploy User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, DeployStatus $status = DeployStatus::Live): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'Deploy Site',
            'slug' => 'deploy-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/deploy',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => $status,
        ]);
    }

    // ── Deploy ────────────────────────────────────

    public function test_owner_can_trigger_manual_deploy(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->post(route('sites.deploy', $site))
            ->assertRedirect();

        Queue::assertPushed(DeploySiteJob::class, function (DeploySiteJob $job) use ($site) {
            return $job->site->is($site) && $job->triggeredBy === 'manual';
        });
    }

    public function test_deploy_sets_status_to_deploying(): void
    {
        Queue::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user, DeployStatus::Live);

        $this->actingAs($user)
            ->post(route('sites.deploy', $site));

        $this->assertSame(DeployStatus::Deploying, $site->fresh()->deploy_status);
    }

    public function test_other_user_cannot_deploy_site(): void
    {
        Queue::fake();

        $owner = $this->makeUser('owner@d.com');
        $other = $this->makeUser('other@d.com');
        $site = $this->makeSite($owner);

        $this->actingAs($other)
            ->post(route('sites.deploy', $site))
            ->assertStatus(404);

        Queue::assertNothingPushed();
    }

    public function test_unauthenticated_cannot_deploy(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->post(route('sites.deploy', $site))->assertRedirect('/login');
    }

    // ── Maintenance ───────────────────────────────

    public function test_maintenance_page_loads(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->get(route('sites.maintenance', $site))
            ->assertOk()
            ->assertViewIs('dashboard.sites.maintenance');
    }

    public function test_owner_can_enable_maintenance_mode(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->put(route('sites.maintenance.update', $site), [
                'enabled' => true,
                'title' => 'Down for maintenance',
                'message' => 'Back soon.',
            ])
            ->assertRedirect();

        $settings = $site->fresh()->maintenance_settings;
        $this->assertTrue((bool) ($settings['enabled'] ?? false));
        $this->assertSame('Down for maintenance', $settings['title']);
    }

    public function test_owner_can_disable_maintenance_mode(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $site->update(['maintenance_settings' => ['enabled' => true, 'title' => '', 'message' => '', 'allowed_ips' => []]]);

        $this->actingAs($user)
            ->put(route('sites.maintenance.update', $site), ['enabled' => false])
            ->assertRedirect();

        $this->assertFalse((bool) ($site->fresh()->maintenance_settings['enabled'] ?? true));
    }

    public function test_maintenance_allowed_ips_are_parsed_from_newlines(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $this->actingAs($user)
            ->put(route('sites.maintenance.update', $site), [
                'enabled' => true,
                'allowed_ips' => "192.168.1.1\n10.0.0.1\n",
            ])
            ->assertRedirect();

        $ips = $site->fresh()->maintenance_settings['allowed_ips'];
        $this->assertContains('192.168.1.1', $ips);
        $this->assertContains('10.0.0.1', $ips);
        $this->assertCount(2, $ips);
    }

    public function test_maintenance_preview_returns_html(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $site->update(['maintenance_settings' => [
            'enabled' => true,
            'title' => 'Maintenance',
            'message' => 'Back soon',
            'allowed_ips' => [],
        ]]);

        $response = $this->actingAs($user)
            ->get(route('sites.maintenance.preview', $site));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_other_user_cannot_update_maintenance(): void
    {
        $owner = $this->makeUser('owner@m.com');
        $other = $this->makeUser('other@m.com');
        $site = $this->makeSite($owner);

        $this->actingAs($other)
            ->put(route('sites.maintenance.update', $site), ['enabled' => true])
            ->assertStatus(404);
    }
}
