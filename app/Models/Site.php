<?php

namespace App\Models;

use App\Enums\DeployStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Site extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'client_name',
        'slug',
        'client_first_name',
        'client_last_name',
        'client_email',
        'client_phone',
        'client_company',
        'client_address',
        'client_notes',
        'repo_url',
        'branch',
        'deploy_on_webhook',
        'github_token',
        'webhook_secret',
        'inbox_inbound_secret',
        'project_type',
        'billing_cycle',
        'monthly_retainer',
        'deployment_mode',
        'build_command',
        'build_output_dir',
        'node_version',
        'env_variables',
        'domain',
        'ssl_provider',
        'dns_provider',
        'ssl_status',
        'uptime_percent',
        'response_avg_ms',
        'response_p95_ms',
        'downtime_minutes',
        'visitors_today',
        'visitors_change_percent',
        'ssl_expires_at',
        'deploy_status',
        'last_deployed_at',
        'last_synced_at',
        'ga_property_id',
        'gtm_id',
        'google_ads_id',
        'cf_zone_id',
        'cf_api_token',
        'gsc_property',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'hosting_provider',
        'ssh_host',
        'ftp_ssh_user',
        'ftp_ssh_password',
        'r2_bucket_prefix',
        'nginx_conf_path',
        'deploy_path',
        'repo_path',
        // pre_deploy_hook and post_deploy_hook are intentionally excluded from $fillable.
        // They are shell command strings that are not yet executed — keeping them out of
        // $fillable prevents any accidental mass-assignment from API or Livewire requests.
        'maintenance_settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'github_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'inbox_inbound_secret' => 'encrypted',
            'cf_api_token' => 'encrypted',
            'smtp_password' => 'encrypted',
            'ftp_ssh_password' => 'encrypted',
            'env_variables' => 'array',
            'monthly_retainer' => 'decimal:2',
            'uptime_percent' => 'decimal:2',
            'response_avg_ms' => 'integer',
            'response_p95_ms' => 'integer',
            'downtime_minutes' => 'integer',
            'visitors_today' => 'integer',
            'visitors_change_percent' => 'float',
            'ssl_expires_at' => 'datetime',
            'last_deployed_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'maintenance_settings' => 'array',
            'is_active' => 'boolean',
            'deploy_on_webhook' => 'boolean',
            'deploy_status'     => DeployStatus::class,
        ];
    }

    protected function clientName(): Attribute
    {
        return Attribute::get(fn () => $this->clientDisplayName());
    }

    protected function type(): Attribute
    {
        return Attribute::get(fn () => (string) $this->project_type);
    }

    protected function status(): Attribute
    {
        return Attribute::get(function () {
            return $this->deploy_status?->label()
                ?? Str::title((string) ($this->getRawOriginal('deploy_status') ?: 'Draft'));
        });
    }

    protected function uptime(): Attribute
    {
        return Attribute::get(fn () => $this->attributes['uptime_percent'] ?? null);
    }

    protected function responseAvg(): Attribute
    {
        return Attribute::get(fn () => $this->attributes['response_avg_ms'] ?? null);
    }

    protected function responseP95(): Attribute
    {
        return Attribute::get(fn () => $this->attributes['response_p95_ms'] ?? null);
    }

    protected function sslOk(): Attribute
    {
        return Attribute::get(fn () => $this->ssl_status === 'active');
    }

    protected function sslDays(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->ssl_expires_at) {
                return 0;
            }

            return max(0, now()->diffInDays($this->ssl_expires_at, false));
        });
    }

    protected function maintenance(): Attribute
    {
        return Attribute::get(fn () => $this->maintenance_settings ?? []);
    }

    protected function projectTypeLabel(): Attribute
    {
        return Attribute::get(function () {
            return match ($this->project_type) {
                'static_html' => 'Static HTML',
                'php_site' => 'PHP Site',
                'react' => 'React',
                'vue' => 'Vue',
                'svelte' => 'Svelte',
                'astro' => 'Astro',
                'hugo' => 'Hugo',
                'eleventy' => '11ty',
                'nextjs' => 'Node.js',
                'nuxt' => 'Nuxt',
                'custom' => 'Custom',
                default => Str::title(str_replace('_', ' ', (string) ($this->project_type ?: 'Project'))),
            };
        });
    }

    // ── Boot ────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            if (empty($site->user_id) && auth()->check()) {
                $site->user_id = (string) auth()->id();
            }
            if (empty($site->slug)) {
                $site->slug = Str::slug($site->name);
            }
            if (empty($site->r2_bucket_prefix)) {
                $site->r2_bucket_prefix = 'sites/'.$site->slug.'/media';
            }
            if (empty($site->repo_path)) {
                $site->repo_path = config('pixelkraft.repos_path').'/'.$site->slug;
            }
            if (empty($site->deploy_path)) {
                $site->deploy_path = config('pixelkraft.sites_deploy_path').'/'.$site->slug;
            }
        });

        static::deleting(function (Site $site): void {
            $site->cleanupFilesystemArtifacts();
        });
    }

    // ── Helpers ──────────────────────────────────

    public function isLive(): bool
    {
        return $this->deploy_status === DeployStatus::Live;
    }

    public function needsBuild(): bool
    {
        return ! empty($this->build_command);
    }

    public function repoOwnerAndName(): array
    {
        // Extract "owner/repo" from GitHub URL
        $path = parse_url($this->repo_url, PHP_URL_PATH);

        return [
            'owner' => trim(dirname($path), '/'),
            'repo' => basename($path, '.git'),
        ];
    }

    public function normalizedGithubRepository(): ?string
    {
        return self::normalizeGithubRepository($this->repo_url);
    }

    public static function normalizeGithubRepository(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Already owner/repo.
        if (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $value)) {
            return strtolower($value);
        }

        // Handle SSH GitHub URLs: git@github.com:owner/repo.git
        if (preg_match('/^git@github\.com:(.+)$/i', $value, $matches)) {
            $path = trim($matches[1], '/');
        } else {
            $parsed = parse_url($value);
            $host = strtolower((string) ($parsed['host'] ?? ''));

            if ($host !== '' && ! str_ends_with($host, 'github.com')) {
                return null;
            }

            $path = trim((string) ($parsed['path'] ?? ''), '/');
        }

        $path = preg_replace('/\.git$/i', '', $path) ?? $path;
        $segments = array_values(array_filter(explode('/', $path)));

        if (count($segments) < 2) {
            return null;
        }

        return strtolower($segments[0].'/'.$segments[1]);
    }

    // ── Relationships ───────────────────────────

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function editSessions()
    {
        return $this->hasMany(EditSession::class);
    }

    public function gitOperations()
    {
        return $this->hasMany(GitOperation::class);
    }

    public function deploymentTargets()
    {
        return $this->hasMany(DeploymentTarget::class);
    }

    public function deploymentReleases()
    {
        return $this->hasMany(DeploymentRelease::class);
    }

    public function trackingInstallations()
    {
        return $this->hasMany(TrackingInstallation::class);
    }

    public function analyticsEvents()
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function webhookDeliveries()
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function blogPosts()
    {
        return $this->hasMany(BlogPost::class);
    }

    public function productListings()
    {
        return $this->hasMany(ProductListing::class);
    }

    public function contentTemplates()
    {
        return $this->hasMany(ContentTemplate::class);
    }

    public function redirects()
    {
        return $this->hasMany(Redirect::class);
    }

    public function formSubmissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function inboxMessages()
    {
        return $this->hasMany(SiteInboxMessage::class);
    }

    public function messages()
    {
        return $this->hasMany(SiteInboxMessage::class)
            ->orderByDesc('message_at')
            ->orderByDesc('created_at');
    }

    public function clientDisplayName(): string
    {
        $directName = trim((string) ($this->attributes['client_name'] ?? ''));
        if ($directName !== '') {
            return $directName;
        }

        $name = trim(trim((string) $this->client_first_name).' '.trim((string) $this->client_last_name));

        if ($name !== '') {
            return $name;
        }

        if (! empty($this->client_company)) {
            return (string) $this->client_company;
        }

        if (! empty($this->client_email)) {
            return (string) $this->client_email;
        }

        return 'Client';
    }

    public function newsletterSubscribers()
    {
        return $this->hasMany(NewsletterSubscriber::class);
    }

    public function newsletterCampaigns()
    {
        return $this->hasMany(NewsletterCampaign::class);
    }

    public function deployLogs()
    {
        return $this->hasMany(DeployLog::class);
    }

    public function deploys()
    {
        return $this->hasMany(DeployLog::class)->latest('created_at');
    }

    public function uptimeChecks()
    {
        return $this->hasMany(UptimeCheck::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function seoIssues()
    {
        return $this->hasMany(SeoIssue::class);
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class)
            ->orderBy('is_done')
            ->orderBy('due_date');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class)->orderByDesc('expense_date');
    }

    public function reports()
    {
        return $this->hasMany(Report::class)->orderByDesc('report_date');
    }

    // ── Computed ─────────────────────────────────

    public function latestDeploy()
    {
        return $this->hasOne(DeployLog::class)->latestOfMany();
    }

    public function activeTrackingInstallation()
    {
        return $this->hasOne(TrackingInstallation::class)
            ->where('provider', 'pixelkraft')
            ->where('is_active', true);
    }

    public function currentDeploymentRelease()
    {
        return $this->hasOne(DeploymentRelease::class)
            ->where('is_current', true)
            ->latestOfMany('activated_at');
    }

    public function latestUptimeCheck()
    {
        return $this->hasOne(UptimeCheck::class)->latestOfMany('checked_at');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public static function findVisibleOrFail(string $id, ?User $user = null): Site
    {
        $user ??= auth()->user();

        return static::query()
            ->visibleTo($user)
            ->findOrFail($id);
    }

    private function cleanupFilesystemArtifacts(): void
    {
        $this->deleteDirectory($this->repo_path, $this->slug);
        $this->deleteDirectory($this->deploy_path, $this->slug);
        $this->deleteDirectory($this->runtimeRootPath(), $this->slug);
        $this->deleteFile($this->runtimePidPath(), $this->slug.'.pid');
        $this->deleteFile($this->runtimeLogPath(), $this->slug.'.log');
        $this->deleteFile($this->nginxConfigPath(), $this->slug.'.conf');
        $this->deleteFile($this->nginxEnabledPath(), $this->slug.'.conf');
    }

    private function runtimeRootPath(): string
    {
        return rtrim((string) config('pixelkraft.runtime.storage_path', storage_path('app/runtime-sites')), '/')
            .'/'.$this->slug;
    }

    private function runtimePidPath(): string
    {
        return rtrim((string) config('pixelkraft.runtime.pid_path', storage_path('app/runtime-pids')), '/')
            .'/'.$this->slug.'.pid';
    }

    private function runtimeLogPath(): string
    {
        return rtrim((string) config('pixelkraft.runtime.log_path', storage_path('logs/runtime-sites')), '/')
            .'/'.$this->slug.'.log';
    }

    private function nginxConfigPath(): string
    {
        return (string) ($this->nginx_conf_path ?: (rtrim((string) config('pixelkraft.nginx_sites_path'), '/').'/'.$this->slug.'.conf'));
    }

    private function nginxEnabledPath(): string
    {
        $enabledDir = str_replace(
            'sites-available',
            'sites-enabled',
            (string) config('pixelkraft.nginx_sites_path')
        );

        return rtrim($enabledDir, '/').'/'.$this->slug.'.conf';
    }

    private function deleteDirectory(?string $path, ?string $expectedBasename = null): void
    {
        $path = trim((string) $path);

        if ($path === '' || in_array($path, ['/', '.'], true)) {
            return;
        }

        if ($expectedBasename !== null && basename($path) !== $expectedBasename) {
            Log::warning("Skipped deleting unexpected directory [{$path}] for site [{$this->slug}]");

            return;
        }

        try {
            if (is_link($path)) {
                @unlink($path);

                return;
            }

            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to delete directory [{$path}] for deleted site [{$this->slug}]", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function deleteFile(?string $path, ?string $expectedBasename = null): void
    {
        $path = trim((string) $path);

        if ($path === '' || in_array($path, ['/', '.'], true)) {
            return;
        }

        if ($expectedBasename !== null && basename($path) !== $expectedBasename) {
            Log::warning("Skipped deleting unexpected file [{$path}] for site [{$this->slug}]");

            return;
        }

        try {
            if (is_link($path) || File::exists($path)) {
                File::delete($path);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to delete file [{$path}] for deleted site [{$this->slug}]", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
