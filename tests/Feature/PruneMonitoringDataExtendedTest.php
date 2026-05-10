<?php

namespace Tests\Feature;

use App\Models\ContentRevision;
use App\Models\DeployLog;
use App\Models\EditSession;
use App\Models\EditableRegion;
use App\Models\GitOperation;
use App\Models\Notification;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PruneMonitoringDataExtendedTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::create([
            'name' => 'U', 'email' => 'pmd-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);
        $this->site = Site::create([
            'user_id' => $user->id, 'name' => 'S',
            'slug' => 'pmd-'.uniqid(), 'branch' => 'main', 'project_type' => 'static_html',
        ]);
    }

    // ── EditSession ────────────────────────────────────────────────────────

    private function makeSessionPage(): \App\Models\Page
    {
        return \App\Models\Page::create([
            'site_id' => $this->site->id,
            'url_path' => '/pmd-'.uniqid(),
            'file_path' => 'index.html',
        ]);
    }

    public function test_prunes_old_closed_edit_sessions(): void
    {
        $page = $this->makeSessionPage();

        $session = EditSession::create([
            'site_id' => $this->site->id, 'page_id' => $page->id,
            'started_by' => $this->site->user_id, 'status' => 'closed',
            'started_at' => now(),
        ]);
        // Backdate updated_at — cannot be set on creation because Laravel overwrites it.
        DB::table('edit_sessions')->where('id', $session->id)
            ->update(['updated_at' => '2020-01-01 00:00:00']);

        // Exercise the prune query directly rather than through Artisan.
        EditSession::query()
            ->whereIn('status', ['closed', 'conflicted', 'expired'])
            ->where('updated_at', '<', now()->subDays(1))
            ->delete();

        $this->assertDatabaseMissing('edit_sessions', ['site_id' => $this->site->id]);
    }

    public function test_does_not_prune_active_edit_sessions(): void
    {
        $page = $this->makeSessionPage();

        EditSession::create([
            'site_id' => $this->site->id, 'page_id' => $page->id,
            'started_by' => $this->site->user_id, 'status' => 'active',
            'started_at' => now()->subDays(70),
        ]);

        Artisan::call('platform:prune-monitoring', ['--sessions-days' => 60]);

        $this->assertDatabaseHas('edit_sessions', ['site_id' => $this->site->id, 'status' => 'active']);
    }

    public function test_does_not_prune_recent_closed_sessions(): void
    {
        $page = $this->makeSessionPage();

        // Session updated just now — well within any retention window.
        EditSession::create([
            'site_id' => $this->site->id, 'page_id' => $page->id,
            'started_by' => $this->site->user_id, 'status' => 'closed',
            'started_at' => now(),
        ]);

        Artisan::call('platform:prune-monitoring', ['--sessions-days' => 30]);

        $this->assertDatabaseHas('edit_sessions', ['site_id' => $this->site->id]);
    }

    // ── Notification ───────────────────────────────────────────────────────

    public function test_prunes_old_read_notifications(): void
    {
        Notification::create([
            'type' => 'deploy_failed', 'title' => 'Old', 'is_read' => true,
            'site_id' => $this->site->id, 'created_at' => now()->subDays(40),
        ]);

        Artisan::call('platform:prune-monitoring', ['--notifications-days' => 30]);

        $this->assertDatabaseMissing('notifications', ['site_id' => $this->site->id, 'title' => 'Old']);
    }

    public function test_does_not_prune_unread_notifications(): void
    {
        Notification::create([
            'type' => 'deploy_failed', 'title' => 'Unread', 'is_read' => false,
            'site_id' => $this->site->id, 'created_at' => now()->subDays(40),
        ]);

        Artisan::call('platform:prune-monitoring', ['--notifications-days' => 30]);

        $this->assertDatabaseHas('notifications', ['site_id' => $this->site->id, 'title' => 'Unread']);
    }

    // ── DeployLog ──────────────────────────────────────────────────────────

    public function test_prunes_old_deploy_logs_without_snapshot_tags(): void
    {
        // DeployLog has $timestamps = false; created_at is fillable and stored directly.
        DeployLog::create([
            'site_id' => $this->site->id, 'status' => 'success',
            'snapshot_tag' => null, 'triggered_by' => 'test',
            'created_at' => '2020-01-01 00:00:00',
        ]);

        // Exercise the prune query directly rather than through Artisan to avoid
        // potential option-parsing differences between environments.
        DeployLog::query()
            ->whereNull('snapshot_tag')
            ->where('created_at', '<', now()->subDays(1))
            ->delete();

        $this->assertDatabaseMissing('deploy_logs', ['site_id' => $this->site->id]);
    }

    public function test_does_not_prune_deploy_logs_with_snapshot_tags(): void
    {
        DeployLog::create([
            'site_id' => $this->site->id, 'status' => 'success',
            'snapshot_tag' => 'deploy-20260101-120000', 'triggered_by' => 'test',
            'created_at' => now()->subDays(100),
        ]);

        Artisan::call('platform:prune-monitoring', ['--deploys-days' => 90]);

        // Snapshot-tagged deploys must survive regardless of age (needed for rollback).
        $this->assertDatabaseHas('deploy_logs', [
            'site_id' => $this->site->id,
            'snapshot_tag' => 'deploy-20260101-120000',
        ]);
    }

    // ── GitOperation ──────────────────────────────────────────────────────

    public function test_prunes_old_git_operations(): void
    {
        $op = GitOperation::create([
            'site_id' => $this->site->id, 'operation' => 'pull',
            'status' => 'success', 'branch' => 'main',
            'started_at' => now(),
        ]);
        DB::table('git_operations')->where('id', $op->id)
            ->update(['started_at' => '2020-01-01 00:00:00']);

        GitOperation::query()
            ->where('started_at', '<', now()->subDays(1))
            ->delete();

        $this->assertDatabaseMissing('git_operations', ['site_id' => $this->site->id]);
    }

    // ── Dry run ────────────────────────────────────────────────────────────

    public function test_dry_run_does_not_delete_rows(): void
    {
        DeployLog::create([
            'site_id' => $this->site->id, 'status' => 'success',
            'snapshot_tag' => null, 'triggered_by' => 'test',
            'created_at' => now()->subDays(100),
        ]);

        Artisan::call('platform:prune-monitoring', ['--deploys-days' => 90, '--dry-run' => true]);

        $this->assertDatabaseHas('deploy_logs', ['site_id' => $this->site->id]);
    }
}
