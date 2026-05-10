<?php

namespace Tests\Unit;

use App\Models\DeployLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeployLogAppendCapTest extends TestCase
{
    use RefreshDatabase;

    private string $siteId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'Test Site',
            'slug' => 'test-site',
            'repo_url' => 'https://github.com/example/test-site.git',
            'branch' => 'main',
            'repo_path' => '/tmp/test-site',
        ]);

        $this->siteId = $site->id;
    }

    public function test_short_output_is_stored_verbatim(): void
    {
        $log = DeployLog::create([
            'site_id' => $this->siteId,
            'status' => 'queued',
            'triggered_by' => 'test',
            'created_at' => now(),
        ]);

        $log->appendLog('Step 1: started');
        $log->appendLog('Step 2: done');
        $log->flushLog(); // flush buffer before reading from DB

        $stored = DeployLog::query()->findOrFail($log->id);
        $this->assertStringContainsString('Step 1: started', $stored->output_log);
        $this->assertStringContainsString('Step 2: done', $stored->output_log);
        $this->assertStringNotContainsString('truncated', $stored->output_log);
    }

    public function test_output_is_capped_at_512kb_and_truncation_notice_prepended(): void
    {
        $log = DeployLog::create([
            'site_id' => $this->siteId,
            'status' => 'building',
            'triggered_by' => 'test',
            'created_at' => now(),
        ]);

        // Fill with ~600 KB in one append so the buffer flushes at the cap threshold.
        $log->appendLog(str_repeat('x', 620 * 1024));
        $log->flushLog();

        $stored = DeployLog::query()->findOrFail($log->id);

        // Must not exceed 512 KB + small overhead for the truncation notice
        $this->assertLessThanOrEqual(512 * 1024 + 200, strlen((string) $stored->output_log));

        // Must contain the truncation sentinel so operators know lines were dropped
        $this->assertStringContainsString('[...log truncated', (string) $stored->output_log);
    }

    public function test_newest_lines_are_kept_when_truncated(): void
    {
        $log = DeployLog::create([
            'site_id' => $this->siteId,
            'status' => 'building',
            'triggered_by' => 'test',
            'created_at' => now(),
        ]);

        $log->appendLog(str_repeat('a', 520 * 1024));
        $log->flushLog();

        $log->appendLog('SENTINEL_LAST_LINE');
        $log->flushLog();

        $stored = DeployLog::query()->findOrFail($log->id);
        $this->assertStringContainsString('SENTINEL_LAST_LINE', (string) $stored->output_log);
    }
}
