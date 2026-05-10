<?php

namespace Tests\Unit;

use App\Models\DeployLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeployLogModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeLog(array $attrs = []): DeployLog
    {
        $user = User::create([
            'name' => 'U',
            'email' => 'dl-'.uniqid().'@x.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);
        $site = Site::create([
            'user_id' => $user->id,
            'name' => 'S',
            'slug' => 'dl-'.uniqid(),
            'repo_url' => 'https://github.com/example/s',
            'branch' => 'main',
            'project_type' => 'static_html',
        ]);

        return DeployLog::create(array_merge([
            'site_id' => $site->id,
            'status' => 'queued',
            'triggered_by' => 'manual',
            'created_at' => now(),
        ], $attrs));
    }

    // ── isSuccess / isFailed ─────────────────────

    public function test_is_success_true_for_success_status(): void
    {
        $log = $this->makeLog(['status' => 'success']);
        $this->assertTrue($log->isSuccess());
        $this->assertFalse($log->isFailed());
    }

    public function test_is_failed_true_for_failed_status(): void
    {
        $log = $this->makeLog(['status' => 'failed']);
        $this->assertTrue($log->isFailed());
        $this->assertFalse($log->isSuccess());
    }

    public function test_is_success_and_is_failed_both_false_for_other_statuses(): void
    {
        foreach (['queued', 'started', 'running'] as $status) {
            $log = $this->makeLog(['status' => $status]);
            $this->assertFalse($log->isSuccess(), "Expected false for status=$status");
            $this->assertFalse($log->isFailed(), "Expected false for status=$status");
        }
    }

    // ── durationFormatted ─────────────────────────

    public function test_duration_formatted_returns_dash_when_null(): void
    {
        $log = $this->makeLog(['duration_ms' => null]);
        $this->assertSame('—', $log->durationFormatted());
    }

    public function test_duration_formatted_shows_seconds_under_60s(): void
    {
        $log = $this->makeLog(['duration_ms' => 4500]);
        $this->assertSame('4.5s', $log->durationFormatted());
    }

    public function test_duration_formatted_shows_minutes_over_60s(): void
    {
        $log = $this->makeLog(['duration_ms' => 90000]); // 90s = 1.5m
        $this->assertSame('1.5m', $log->durationFormatted());
    }

    public function test_duration_formatted_exactly_one_minute(): void
    {
        $log = $this->makeLog(['duration_ms' => 60000]);
        $this->assertSame('1m', $log->durationFormatted());
    }

    // ── appendLog ────────────────────────────────

    public function test_append_log_adds_lines(): void
    {
        $log = $this->makeLog();
        $log->appendLog('Step 1');
        $log->appendLog('Step 2');
        $log->flushLog(); // buffer must be flushed before reading from DB

        $this->assertStringContainsString('Step 1', $log->fresh()->output_log);
        $this->assertStringContainsString('Step 2', $log->fresh()->output_log);
    }

    public function test_append_log_caps_at_512kb(): void
    {
        $log = $this->makeLog();

        // Write more than 512 KB in one flush (single line > threshold triggers truncation in flushLog)
        $bigLine = str_repeat('x', 520 * 1024);
        $log->appendLog($bigLine);
        $log->flushLog();

        $fresh = $log->fresh();
        $this->assertLessThanOrEqual(512 * 1024 + 200, strlen($fresh->output_log));
        $this->assertStringContainsString('[...log truncated', $fresh->output_log);
    }

    public function test_append_log_preserves_newest_lines_when_truncated(): void
    {
        $log = $this->makeLog();

        $bigLine = str_repeat('a', 520 * 1024);
        $log->appendLog($bigLine);
        $log->flushLog();

        $log->appendLog('LAST LINE');
        $log->flushLog();

        $this->assertStringContainsString('LAST LINE', $log->fresh()->output_log);
    }
}
