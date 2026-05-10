<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $site_id
 * @property string $status
 * @property string|null $commit_sha
 * @property string|null $commit_message
 * @property string|null $output_log
 * @property int|null $duration_ms
 * @property string|null $triggered_by
 * @property string|null $snapshot_tag
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Site|null $site
 */
class DeployLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'status',
        'commit_sha',
        'commit_message',
        'output_log',
        'duration_ms',
        'triggered_by',
        'snapshot_tag',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected function hash(): Attribute
    {
        return Attribute::get(function () {
            $value = $this->commit_sha ?: $this->id;

            return $value ? Str::limit((string) $value, 7, '') : null;
        });
    }

    protected function duration(): Attribute
    {
        return Attribute::get(fn () => $this->durationFormatted());
    }

    protected function time(): Attribute
    {
        return Attribute::get(fn () => $this->created_at);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function durationFormatted(): string
    {
        if (! $this->duration_ms) {
            return '—';
        }

        $seconds = $this->duration_ms / 1000;

        return $seconds >= 60
            ? round($seconds / 60, 1).'m'
            : round($seconds, 1).'s';
    }

    /** Maximum bytes stored in output_log before the oldest lines are dropped. */
    private const MAX_LOG_BYTES = 512 * 1024; // 512 KB

    /** Buffer up to this many lines before flushing to DB. */
    private const LOG_FLUSH_BATCH = 8;

    /** @var list<string> */
    private array $logBuffer = [];

    /**
     * Append a line to the deploy log.  Lines are buffered in memory and
     * flushed to the database every LOG_FLUSH_BATCH lines to reduce the
     * number of UPDATE queries during a deploy (previously one per call).
     * Call flushLog() at the end of each deploy step to ensure lines are
     * persisted before the next step's job picks up the model.
     */
    public function appendLog(string $line): void
    {
        $this->logBuffer[] = $line;

        if (count($this->logBuffer) >= self::LOG_FLUSH_BATCH) {
            $this->flushLog();
        }
    }

    /**
     * Write any buffered log lines to the database immediately.
     * Must be called at the end of each deploy step (before ->fresh() or
     * model hand-off between queue jobs).
     */
    public function flushLog(): void
    {
        if (empty($this->logBuffer)) {
            return;
        }

        $lines = implode("\n", $this->logBuffer)."\n";
        $this->logBuffer = [];

        $current = ($this->output_log ?? '').$lines;

        if (strlen($current) > self::MAX_LOG_BYTES) {
            $truncated = substr($current, -self::MAX_LOG_BYTES);
            $nl = strpos($truncated, "\n");
            $truncated = $nl !== false ? substr($truncated, $nl + 1) : $truncated;
            $current = "[...log truncated to last 512 KB...]\n".$truncated;
        }

        $this->output_log = $current;
        $this->save();
    }
}
