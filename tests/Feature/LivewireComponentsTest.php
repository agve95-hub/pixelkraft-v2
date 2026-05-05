<?php

namespace Tests\Feature;

use App\Enums\DeployStatus;
use App\Jobs\DeploySiteJob;
use App\Livewire\Email\CampaignEditor;
use App\Livewire\Email\SubscriberList;
use App\Livewire\Sites\DeployControls;
use App\Livewire\Sites\InvoiceManager;
use App\Livewire\Sites\MaintenanceMode;
use App\Models\Invoice;
use App\Models\NewsletterSubscriber;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireComponentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email = 'lw@example.com'): User
    {
        return User::create([
            'name' => 'LW User',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'LW Site',
            'slug' => 'lw-site-'.uniqid(),
            'repo_url' => 'https://github.com/example/lw',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => DeployStatus::Live,
        ], $attrs));
    }

    // ── DeployControls ────────────────────────────

    public function test_deploy_controls_renders(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        Livewire::actingAs($user)
            ->test(DeployControls::class, ['siteId' => $site->id])
            ->assertOk();
    }

    public function test_deploy_controls_dispatches_job(): void
    {
        Queue::fake();

        $user = $this->makeUser('dep@lw.com');
        $site = $this->makeSite($user);

        Livewire::actingAs($user)
            ->test(DeployControls::class, ['siteId' => $site->id])
            ->call('deploy');

        Queue::assertPushed(DeploySiteJob::class);
    }

    public function test_deploy_controls_blocks_when_already_deploying(): void
    {
        Queue::fake();

        $user = $this->makeUser('dep2@lw.com');
        $site = $this->makeSite($user, ['deploy_status' => DeployStatus::Building]);

        Livewire::actingAs($user)
            ->test(DeployControls::class, ['siteId' => $site->id])
            ->call('deploy');

        // Should not dispatch when already in progress
        Queue::assertNothingPushed();
    }

    // ── InvoiceManager ────────────────────────────

    public function test_invoice_manager_renders_index_screen(): void
    {
        $user = $this->makeUser('inv@lw.com');
        $site = $this->makeSite($user);

        Livewire::actingAs($user)
            ->test(InvoiceManager::class, ['siteId' => $site->id])
            ->assertSet('screen', 'index')
            ->assertOk();
    }

    public function test_invoice_manager_shows_existing_invoices(): void
    {
        $user = $this->makeUser('inv2@lw.com');
        $site = $this->makeSite($user);

        Invoice::create([
            'site_id' => $site->id,
            'number' => 'INV-001',
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'EUR',
            'status' => 'unpaid',
            'tax_rate' => 0,
            'discount_percent' => 0,
            'payment_terms' => 'net30',
        ]);

        Livewire::actingAs($user)
            ->test(InvoiceManager::class, ['siteId' => $site->id])
            ->assertSee('INV-001');
    }

    // ── MaintenanceMode ───────────────────────────

    public function test_maintenance_mode_renders(): void
    {
        $user = $this->makeUser('maint@lw.com');
        $site = $this->makeSite($user);

        Livewire::actingAs($user)
            ->test(MaintenanceMode::class, ['siteId' => $site->id])
            ->assertOk();
    }

    public function test_maintenance_mode_can_save_enabled(): void
    {
        $user = $this->makeUser('maint2@lw.com');
        $site = $this->makeSite($user, [
            'maintenance_settings' => ['enabled' => false, 'title' => '', 'message' => '', 'allowed_ips' => []],
        ]);

        Livewire::actingAs($user)
            ->test(MaintenanceMode::class, ['siteId' => $site->id])
            ->set('enabled', true)
            ->call('save');

        $this->assertTrue((bool) ($site->fresh()->maintenance_settings['enabled'] ?? false));
    }

    // ── SubscriberList ────────────────────────────

    public function test_subscriber_list_renders_for_site(): void
    {
        $user = $this->makeUser('sub@lw.com');
        $site = $this->makeSite($user);

        Livewire::actingAs($user)
            ->test(SubscriberList::class, ['siteId' => $site->id])
            ->assertOk();
    }

    public function test_subscriber_list_can_add_subscriber(): void
    {
        $user = $this->makeUser('sub2@lw.com');
        $site = $this->makeSite($user);

        Livewire::actingAs($user)
            ->test(SubscriberList::class, ['siteId' => $site->id])
            ->set('newEmail', 'new@subscriber.com')
            ->call('addSubscriber');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'site_id' => $site->id,
            'email' => 'new@subscriber.com',
            'status' => 'active',
        ]);
    }

    public function test_subscriber_list_can_unsubscribe(): void
    {
        $user = $this->makeUser('sub3@lw.com');
        $site = $this->makeSite($user);

        $sub = NewsletterSubscriber::create([
            'site_id' => $site->id,
            'email' => 'active@sub.com',
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(SubscriberList::class, ['siteId' => $site->id])
            ->call('unsubscribe', $sub->id);

        $this->assertSame('unsubscribed', $sub->fresh()->status);
    }

    public function test_subscriber_list_can_delete_subscriber(): void
    {
        $user = $this->makeUser('sub4@lw.com');
        $site = $this->makeSite($user);

        $sub = NewsletterSubscriber::create([
            'site_id' => $site->id,
            'email' => 'delete@sub.com',
            'status' => 'active',
        ]);

        Livewire::actingAs($user)
            ->test(SubscriberList::class, ['siteId' => $site->id])
            ->call('delete', $sub->id);

        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $sub->id]);
    }

    // ── CampaignEditor ────────────────────────────

    public function test_campaign_editor_renders_for_site(): void
    {
        $user = $this->makeUser('camp@lw.com');
        $site = $this->makeSite($user);

        Livewire::actingAs($user)
            ->test(CampaignEditor::class, ['siteId' => $site->id])
            ->assertOk();
    }
}
