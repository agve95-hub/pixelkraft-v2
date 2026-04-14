<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SiteInboxMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id',
        'user_id',
        'direction',
        'from_email',
        'from_name',
        'to_email',
        'subject',
        'body',
        'is_read',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function listSenderLabel(): string
    {
        if ($this->direction === 'outbound') {
            return 'You';
        }

        $name = trim((string) $this->from_name);

        if ($name !== '') {
            return $name;
        }

        return $this->from_email ?: 'Unknown';
    }

    public function previewText(int $limit = 140): string
    {
        $flat = preg_replace('/\s+/', ' ', strip_tags($this->body));
        $flat = is_string($flat) ? trim($flat) : '';

        return Str::limit($flat, $limit);
    }

    public static function subjectFromFormPayload(array $data, string $formName): string
    {
        foreach (['subject', 'title', 'topic'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                return Str::limit(trim($data[$key]), 200);
            }
        }

        return 'Contact: '.str_replace('_', ' ', $formName);
    }

    public static function bodyFromFormPayload(array $data): string
    {
        foreach (['message', 'body', 'content', 'inquiry', 'comments', 'details'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                return trim($data[$key]);
            }
        }

        $lines = [];
        foreach ($data as $key => $value) {
            if (str_starts_with((string) $key, '_') || in_array($key, ['subject', 'title', 'topic'], true)) {
                continue;
            }
            if (is_string($value) || is_numeric($value)) {
                $lines[] = ucfirst(str_replace('_', ' ', (string) $key)).': '.$value;
            }
        }

        return $lines !== [] ? implode("\n", $lines) : '(No message body)';
    }
}
