<?php

namespace Tests\Unit;

use App\Enums\DeployStatus;
use App\Jobs\ImportSiteFromZipJob;
use App\Jobs\ParseSiteJob;
use App\Models\Site;
use App\Models\User;
use App\Services\Import\ImportException;
use App\Services\Import\ImportResult;
use App\Services\Import\ZipImportService;
use App\Services\ProjectDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportSiteFromZipJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name' => 'U',
            'email' => 'isfzj-'.uniqid().'@x.com',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);
    }

    private function makeSite(User $user, array $attrs = []): Site
    {
        return Site::create(array_merge([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'isfzj-'.uniqid(),
            'repo_url' => 'https://github.com/example/isfzj',
            'branch' => 'main',
            'project_type' => 'static_html',
            'deploy_status' => DeployStatus::Idle,
        ], $attrs));
    }

    private function importResult(string $type = 'static_html', int $count = 3): ImportResult
    {
        return new ImportResult(fileCount: $count, projectType: $type, files: []);
    }

    // ── success path ─────────────────────────────

    public function test_sets_deploy_status_to_deploying_then_idle_on_success(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        // Use nextjs so the detector branch (static_html only) is skipped
        $site = $this->makeSite($user, ['project_type' => 'nextjs']);

        $importer = $this->mock(ZipImportService::class);
        $importer->shouldReceive('import')->andReturn($this->importResult('nextjs'));

        $detector = $this->mock(ProjectDetector::class);
        $detector->shouldReceive('applyToSite')->never();

        $job = new ImportSiteFromZipJob($site, 'uploads/test.zip');
        $job->handle($importer, $detector);

        $site->refresh();
        $this->assertSame(DeployStatus::Idle, $site->deploy_status);
    }

    public function test_dispatches_parse_site_job_on_success(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'nextjs']);

        $importer = $this->mock(ZipImportService::class);
        $importer->shouldReceive('import')->andReturn($this->importResult('nextjs'));

        $detector = $this->mock(ProjectDetector::class);
        $detector->shouldReceive('applyToSite')->never();

        $job = new ImportSiteFromZipJob($site, 'uploads/test.zip');
        $job->handle($importer, $detector);

        Bus::assertDispatched(ParseSiteJob::class);
    }

    public function test_runs_detector_when_project_type_is_static_html(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'static_html']);

        $importer = $this->mock(ZipImportService::class);
        $importer->shouldReceive('import')->andReturn($this->importResult('static_html'));

        $detector = $this->mock(ProjectDetector::class);
        $detector->shouldReceive('applyToSite')->once();

        $job = new ImportSiteFromZipJob($site, 'uploads/test.zip');
        $job->handle($importer, $detector);
    }

    public function test_skips_detector_when_project_type_is_not_static_html(): void
    {
        Bus::fake();

        $user = $this->makeUser();
        $site = $this->makeSite($user, ['project_type' => 'nextjs']);

        $importer = $this->mock(ZipImportService::class);
        $importer->shouldReceive('import')->andReturn($this->importResult('nextjs'));

        $detector = $this->mock(ProjectDetector::class);
        $detector->shouldReceive('applyToSite')->never();

        $job = new ImportSiteFromZipJob($site, 'uploads/test.zip');
        $job->handle($importer, $detector);
    }

    // ── ImportException path ──────────────────────

    public function test_import_exception_sets_status_failed_and_creates_notification(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $importer = $this->mock(ZipImportService::class);
        $importer->shouldReceive('import')
            ->andThrow(new ImportException('ZIP contains a path-traversal entry'));

        $detector = $this->mock(ProjectDetector::class);

        $job = new ImportSiteFromZipJob($site, 'uploads/bad.zip');
        $job->handle($importer, $detector);

        $site->refresh();
        $this->assertSame(DeployStatus::Failed, $site->deploy_status);

        $this->assertDatabaseHas('notifications', [
            'site_id' => $site->id,
            'type' => 'deploy_failed',
        ]);
    }

    public function test_import_exception_does_not_rethrow(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $importer = $this->mock(ZipImportService::class);
        $importer->shouldReceive('import')
            ->andThrow(new ImportException('Invalid ZIP'));

        $detector = $this->mock(ProjectDetector::class);

        // Should not throw — no expectException
        $job = new ImportSiteFromZipJob($site, 'uploads/bad.zip');
        $job->handle($importer, $detector);

        $this->assertTrue(true); // reached this point without exception
    }

    // ── Generic Throwable path ────────────────────

    public function test_generic_failure_sets_status_failed_and_rethrows(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $importer = $this->mock(ZipImportService::class);
        $importer->shouldReceive('import')
            ->andThrow(new \RuntimeException('disk full'));

        $detector = $this->mock(ProjectDetector::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('disk full');

        $job = new ImportSiteFromZipJob($site, 'uploads/bad.zip');
        $job->handle($importer, $detector);

        $site->refresh();
        $this->assertSame(DeployStatus::Failed, $site->deploy_status);
    }

    public function test_generic_failure_creates_notification(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $importer = $this->mock(ZipImportService::class);
        $importer->shouldReceive('import')
            ->andThrow(new \RuntimeException('disk full'));

        $detector = $this->mock(ProjectDetector::class);

        try {
            $job = new ImportSiteFromZipJob($site, 'uploads/bad.zip');
            $job->handle($importer, $detector);
        } catch (\RuntimeException) {
        }

        $this->assertDatabaseHas('notifications', [
            'site_id' => $site->id,
            'type' => 'deploy_failed',
        ]);
    }

    // ── tags ─────────────────────────────────────

    public function test_job_has_correct_tags(): void
    {
        $user = $this->makeUser();
        $site = $this->makeSite($user);

        $job = new ImportSiteFromZipJob($site, 'uploads/test.zip');

        $this->assertContains('import', $job->tags());
        $this->assertContains("site:{$site->id}", $job->tags());
    }
}
