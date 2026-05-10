<?php

namespace Tests\Unit;

use App\Models\DeployLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeployLogBufferingTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(): DeployLog
    {
        $user = User::create([
            'name' => 'U', 'email' => 'dlb-'.uniqid().'@x.com',
            'password' => Hash::make('secret'), 'role' => 'admin',
        ]);
        $site = Site::create([
            'user_id' => $user->id, 'name' => 'S',
            'slug' => 'dlb-'.uniqid(), 'branch' => 'main', 'project_type' => 'static_html',
        ]);

        return DeployLog::create([
            'site_id' => $site->id, 'status' => 'queued',
            'triggered_by' => 'test', 'created_at' => now(),
        ]);
    }

    public function test_append_log_does_not_save_until_batch_is_full(): void
    {
        $log = $this->makeLog();
        $queryCount = 0;

        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        // Add 7 lines — one less than LOG_FLUSH_BATCH (8); should NOT hit the DB yet.
        for ($i = 0; $i < 7; $i++) {
            $log->appendLog("line {$i}");
        }

        // No save should have happened.
        $fresh = DeployLog::find($log->id);
        $this->assertNull($fresh->output_log);
    }

    public function test_append_log_flushes_automatically_when_batch_is_full(): void
    {
        $log = $this->makeLog();

        // Fill the batch (8 lines triggers a flush).
        for ($i = 0; $i < 8; $i++) {
            $log->appendLog("line {$i}");
        }

        $fresh = DeployLog::find($log->id);
        $this->assertNotNull($fresh->output_log);
        $this->assertStringContainsString('line 0', $fresh->output_log);
        $this->assertStringContainsString('line 7', $fresh->output_log);
    }

    public function test_flush_log_persists_buffered_lines(): void
    {
        $log = $this->makeLog();
        $log->appendLog('alpha');
        $log->appendLog('beta');

        // Buffer has 2 lines — below the flush threshold.
        $this->assertNull(DeployLog::find($log->id)->output_log);

        $log->flushLog();

        $fresh = DeployLog::find($log->id);
        $this->assertStringContainsString('alpha', $fresh->output_log);
        $this->assertStringContainsString('beta', $fresh->output_log);
    }

    public function test_flush_log_is_idempotent_when_buffer_is_empty(): void
    {
        $log = $this->makeLog();
        $log->flushLog();
        $log->flushLog(); // second call should be a no-op
        $this->assertNull(DeployLog::find($log->id)->output_log);
    }

    public function test_output_log_is_truncated_at_512kb(): void
    {
        $log = $this->makeLog();

        // Write 700 KB of content in one flush.
        $log->appendLog(str_repeat('X', 716_800));
        $log->flushLog();

        $fresh = DeployLog::find($log->id);
        $this->assertNotNull($fresh->output_log);
        $this->assertLessThanOrEqual(540_000, strlen((string) $fresh->output_log));
        $this->assertStringContainsString('[...log truncated', $fresh->output_log);
    }

    public function test_subsequent_lines_are_appended_after_flush(): void
    {
        $log = $this->makeLog();
        $log->appendLog('first');
        $log->flushLog();
        $log->appendLog('second');
        $log->flushLog();

        $fresh = DeployLog::find($log->id);
        $this->assertStringContainsString('first', $fresh->output_log);
        $this->assertStringContainsString('second', $fresh->output_log);
    }
}
