<?php

namespace Tests\Unit;

use App\Models\EditSession;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\EditSessionService;
use App\Services\GitSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EditSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'admin'): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'ess-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => $role,
        ]);
    }

    private function makeSite(User $user): Site
    {
        return Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'ess-'.uniqid(),
            'repo_url' => 'https://github.com/example/s',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);
    }

    private function makePage(Site $site): Page
    {
        return Page::create([
            'site_id' => $site->id,
            'file_path' => 'index.html',
            'url_path' => '/',
            'title' => 'Home',
        ]);
    }

    private function makeService(bool $isCloned = false, ?string $sha = null): EditSessionService
    {
        $git = $this->mock(GitSyncService::class);
        $git->shouldReceive('isCloned')->andReturn($isCloned);

        if ($isCloned) {
            $git->shouldReceive('currentCommitSha')->andReturn($sha ?? 'abc123');
        }

        return new EditSessionService($git);
    }

    // ── startOrResume ────────────────────────────

    public function test_start_creates_new_edit_session(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $service = $this->makeService();

        $session = $service->startOrResume($site, $page, $user);

        $this->assertInstanceOf(EditSession::class, $session);
        $this->assertSame('active', $session->status);
        $this->assertSame($site->id, $session->site_id);
        $this->assertSame($page->id, $session->page_id);
        $this->assertSame($user->id, $session->started_by);
    }

    public function test_start_resumes_existing_active_session(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $service = $this->makeService();

        $first = $service->startOrResume($site, $page, $user);
        $second = $service->startOrResume($site, $page, $user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EditSession::count());
    }

    public function test_start_captures_base_commit_sha_when_repo_cloned(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $service = $this->makeService(isCloned: true, sha: 'deadbeef');

        $session = $service->startOrResume($site, $page, $user);

        $this->assertSame('deadbeef', $session->base_commit_sha);
    }

    public function test_start_null_commit_sha_when_repo_not_cloned(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $service = $this->makeService(isCloned: false);

        $session = $service->startOrResume($site, $page, $user);

        $this->assertNull($session->base_commit_sha);
    }

    public function test_start_stores_page_file_path_in_metadata(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $service = $this->makeService();

        $session = $service->startOrResume($site, $page, $user);

        $this->assertSame('index.html', $session->metadata['page_file_path']);
    }

    public function test_start_generates_working_branch_name(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $service = $this->makeService();

        $session = $service->startOrResume($site, $page, $user);

        $this->assertStringStartsWith("platform/{$site->slug}/", $session->working_branch);
    }

    public function test_start_does_not_resume_closed_session(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $service = $this->makeService();

        $first = $service->startOrResume($site, $page, $user);
        $service->close($first);

        $second = $service->startOrResume($site, $page, $user);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, EditSession::count());
    }

    // ── markConflict / close ─────────────────────

    public function test_mark_conflict_sets_status_to_conflicted(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $service = $this->makeService();

        $session = $service->startOrResume($site, $page, $user);
        $service->markConflict($session, ['reason' => 'remote changed']);

        $session->refresh();
        $this->assertSame('conflicted', $session->status);
        $this->assertNotNull($session->ended_at);
        $this->assertSame('remote changed', $session->metadata['reason']);
    }

    public function test_close_sets_status_to_closed(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);
        $page = $this->makePage($site);
        $service = $this->makeService();

        $session = $service->startOrResume($site, $page, $user);
        $service->close($session);

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertNotNull($session->ended_at);
    }
}
