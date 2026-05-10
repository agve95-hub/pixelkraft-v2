<?php

namespace App\Models;

use App\Enums\DeployStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property string|null $client_name
 * @property string $slug
 * @property string|null $client_first_name
 * @property string|null $client_last_name
 * @property string|null $client_email
 * @property string|null $client_phone
 * @property string|null $client_company
 * @property string|null $client_address
 * @property string|null $client_notes
 * @property string|null $repo_url
 * @property string|null $branch
 * @property bool $deploy_on_webhook
 * @property string|null $github_token
 * @property string|null $webhook_secret
 * @property string|null $inbox_inbound_secret
 * @property string|null $project_type
 * @property string|null $billing_cycle
 * @property string|null $monthly_retainer
 * @property string|null $deployment_mode
 * @property string|null $build_command
 * @property string|null $build_output_dir
 * @property string|null $node_version
 * @property array|null $env_variables
 * @property string|null $domain
 * @property string|null $ssl_provider
 * @property string|null $dns_provider
 * @property string|null $ssl_status
 * @property string|null $uptime_percent
 * @property int|null $response_avg_ms
 * @property int|null $response_p95_ms
 * @property int|null $downtime_minutes
 * @property int|null $visitors_today
 * @property float|null $visitors_change_percent
 * @property Carbon|null $ssl_expires_at
 * @property DeployStatus|null $deploy_status
 * @property Carbon|null $last_deployed_at
 * @property Carbon|null $last_synced_at
 * @property string|null $ga_property_id
 * @property string|null $gtm_id
 * @property string|null $google_ads_id
 * @property string|null $cf_zone_id
 * @property string|null $cf_api_token
 * @property string|null $gsc_property
 * @property string|null $smtp_host
 * @property int|null $smtp_port
 * @property string|null $smtp_username
 * @property string|null $smtp_password
 * @property string|null $hosting_provider
 * @property string|null $ssh_host
 * @property string|null $ftp_ssh_user
 * @property string|null $ftp_ssh_password
 * @property string|null $r2_bucket_prefix
 * @property string|null $nginx_conf_path
 * @property string|null $deploy_path
 * @property string|null $repo_path
 * @property array|null $maintenance_settings
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $pages_count
 * @property-read Collection<int, Page> $pages
 * @property-read DeployLog|null $latestDeploy
 * @property-read UptimeCheck|null $latestUptimeCheck
 */
class Site extends Model
{
    use HasFactory, HasUuids;

    // ── Field groups ─────────────────────────────────────────────────────────
    // Fields are grouped by who may write them so that mass-assignment blast
    // radius stays legible.  Use the typed update helpers below (updateSettings,
    // updateBuildConfig, etc.) instead of direct ->update() calls where possible.

    /** Fields any authenticated owner may change via the dashboard. */
    public const OWNER_FILLABLE = [
        'name', 'slug', 'domain', 'is_active', 'maintenance_settings',
        'deploy_on_webhook', 'deployment_mode', 'project_type', 'source_type',
        'branch', 'deploy_path', 'repo_path', 'nginx_conf_path',
        'ga_property_id', 'gtm_id', 'google_ads_id', 'gsc_property',
        'hosting_provider', 'ssh_host', 'ftp_ssh_user', 'r2_bucket_prefix',
        'billing_cycle', 'monthly_retainer',
        'client_name', 'client_first_name', 'client_last_name',
        'client_email', 'client_phone', 'client_company',
        'client_address', 'client_notes',
        'smtp_host', 'smtp_port', 'smtp_username',
    ];

    /** Fields that execute shell code on the server — admin only. */
    public const BUILD_FILLABLE = [
        'build_command', 'build_output_dir', 'node_version', 'env_variables',
    ];

    /** Encrypted secrets — written via explicit setters, never mass-assigned. */
    public const SECRET_FILLABLE = [
        'github_token', 'webhook_secret', 'inbox_inbound_secret',
        'cf_api_token', 'smtp_password', 'ftp_ssh_password',
    ];

    /** System-managed fields written by queue jobs / services, not user input. */
    public const SYSTEM_FILLABLE = [
        'user_id', 'repo_url', 'repo_slug',
        'ssl_provider', 'dns_provider', 'ssl_status', 'ssl_expires_at',
        'uptime_percent', 'response_avg_ms', 'response_p95_ms', 'downtime_minutes',
        'visitors_today', 'visitors_change_percent',
        'deploy_status', 'last_deployed_at', 'last_synced_at',
        'cf_zone_id',
    ];

    protected $fillable = [
        ...self::OWNER_FILLABLE,
        ...self::BUILD_FILLABLE,
        ...self::SECRET_FILLABLE,
        ...self::SYSTEM_FILLABLE,
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
            'deploy_status' => DeployStatus::class,
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

    // ── Typed update helpers ─────────────────────
    // Use these instead of raw ->update(['field' => $value]) so the intent is
    // clear at the call site and accidental mass-assignment of wrong field groups
    // is caught at review time.

    /** @param  array<string, mixed>  $data */
    public function updateSettings(array $data): bool
    {
        return $this->update(array_intersect_key($data, array_flip(self::OWNER_FILLABLE)));
    }

    /** @param  array<string, mixed>  $data  Admin only — validated by SitePolicy::configureBuild(). */
    public function updateBuildConfig(array $data): bool
    {
        return $this->update(array_intersect_key($data, array_flip(self::BUILD_FILLABLE)));
    }

    /** Written by queue jobs after deploy/sync — no user input. */
    public function updateSystemFields(array $data): bool
    {
        return $this->update(array_intersect_key($data, array_flip(self::SYSTEM_FILLABLE)));
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
            if (empty($site->repo_slug) && ! empty($site->repo_url)) {
                $site->repo_slug = self::normalizeGithubRepository($site->repo_url);
            }
            if (empty($site->r2_bucket_prefix)) {
                $site->r2_bucket_prefix = 'sites/'.$site->slug.'/media';
            }
            if (empty($site->repo_path)) {
                $site->repo_path = config('platform.repos_path').'/'.$site->slug;
            }
            if (empty($site->deploy_path)) {
                $site->deploy_path = config('platform.sites_deploy_path').'/'.$site->slug;
            }
        });

        static::saving(function (Site $site) {
            if ($site->isDirty('repo_url')) {
                $site->repo_slug = self::normalizeGithubRepository($site->repo_url);
            }
        });

        static::deleting(function (Site $site): void {
            $site->cleanupFilesystemArtifacts();
        });
    }

    // ── Helpers ──────────────────────────────────

    /**
     * Transition deploy_status with guard — throws if the transition is not allowed.
     */
    public function transitionDeployStatus(DeployStatus $next): void
    {
        $current = $this->deploy_status ?? DeployStatus::Draft;

        if (! $current->canTransitionTo($next)) {
            throw new \LogicException(
                "Cannot transition deploy_status from [{$current->value}] to [{$next->value}] on site [{$this->slug}]."
            );
        }

        $this->update(['deploy_status' => $next]);
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

    /** @return HasMany<Page, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    /** @return HasMany<EditSession, $this> */
    public function editSessions(): HasMany
    {
        return $this->hasMany(EditSession::class);
    }

    /** @return HasMany<GitOperation, $this> */
    public function gitOperations(): HasMany
    {
        return $this->hasMany(GitOperation::class);
    }

    /** @return HasMany<DeploymentTarget, $this> */
    public function deploymentTargets(): HasMany
    {
        return $this->hasMany(DeploymentTarget::class);
    }

    /** @return HasMany<DeploymentRelease, $this> */
    public function deploymentReleases(): HasMany
    {
        return $this->hasMany(DeploymentRelease::class);
    }

    /** @return HasMany<TrackingInstallation, $this> */
    public function trackingInstallations(): HasMany
    {
        return $this->hasMany(TrackingInstallation::class);
    }

    /** @return HasMany<AnalyticsEvent, $this> */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    /** @return HasMany<WebhookDelivery, $this> */
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<BlogPost, $this> */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    /** @return HasMany<ProductListing, $this> */
    public function productListings(): HasMany
    {
        return $this->hasMany(ProductListing::class);
    }

    /** @return HasMany<ContentTemplate, $this> */
    public function contentTemplates(): HasMany
    {
        return $this->hasMany(ContentTemplate::class);
    }

    /** @return HasMany<Redirect, $this> */
    public function redirects(): HasMany
    {
        return $this->hasMany(Redirect::class);
    }

    /** @return HasMany<FormSubmission, $this> */
    public function formSubmissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /** @return HasMany<SiteInboxMessage, $this> */
    public function inboxMessages(): HasMany
    {
        return $this->hasMany(SiteInboxMessage::class);
    }

    /** @return HasMany<SiteInboxMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SiteInboxMessage::class)
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

    /** @return HasMany<NewsletterSubscriber, $this> */
    public function newsletterSubscribers(): HasMany
    {
        return $this->hasMany(NewsletterSubscriber::class);
    }

    /** @return HasMany<NewsletterCampaign, $this> */
    public function newsletterCampaigns(): HasMany
    {
        return $this->hasMany(NewsletterCampaign::class);
    }

    /** @return HasMany<DeployLog, $this> */
    public function deployLogs(): HasMany
    {
        return $this->hasMany(DeployLog::class);
    }

    /** @return HasMany<DeployLog, $this> */
    public function deploys(): HasMany
    {
        return $this->hasMany(DeployLog::class)->latest('created_at');
    }

    /** @return HasMany<UptimeCheck, $this> */
    public function uptimeChecks(): HasMany
    {
        return $this->hasMany(UptimeCheck::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** @return HasMany<SeoIssue, $this> */
    public function seoIssues(): HasMany
    {
        return $this->hasMany(SeoIssue::class);
    }

    /** @return HasMany<Reminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class)
            ->orderBy('is_done')
            ->orderBy('due_date');
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)->orderByDesc('expense_date');
    }

    /** @return HasMany<Report, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class)->orderByDesc('report_date');
    }

    /** @return HasMany<Campaign, $this> */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /** @return HasMany<Announcement, $this> */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    // ── Computed ─────────────────────────────────

    /** @return HasOne<DeployLog, $this> */
    public function latestDeploy(): HasOne
    {
        return $this->hasOne(DeployLog::class)->latestOfMany();
    }

    /** @return HasOne<TrackingInstallation, $this> */
    public function activeTrackingInstallation(): HasOne
    {
        return $this->hasOne(TrackingInstallation::class)
            ->where('provider', 'platform')
            ->where('is_active', true);
    }

    /** @return HasOne<DeploymentRelease, $this> */
    public function currentDeploymentRelease(): HasOne
    {
        return $this->hasOne(DeploymentRelease::class)
            ->where('is_current', true)
            ->latestOfMany('activated_at');
    }

    /** @return HasOne<UptimeCheck, $this> */
    public function latestUptimeCheck(): HasOne
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

    private function isplatformOwnedPath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        $normalize = fn (string $p): string => rtrim(str_replace('\\', '/', $p), '/');

        $normalized = $normalize($path);
        $storageRoot = $normalize(storage_path(''));

        return str_starts_with($normalized, $storageRoot.'/') || $normalized === $storageRoot;
    }

    private function cleanupFilesystemArtifacts(): void
    {
        if ($this->isplatformOwnedPath($this->repo_path)) {
            $this->deleteDirectory($this->repo_path, $this->slug);
        } else {
            Log::info("Skipped cleanup of non-platform repo_path [{$this->repo_path}] for site [{$this->slug}]");
        }

        if ($this->isplatformOwnedPath($this->deploy_path)) {
            $this->deleteDirectory($this->deploy_path, $this->slug);
        } else {
            Log::info("Skipped cleanup of non-platform deploy_path [{$this->deploy_path}] for site [{$this->slug}]");
        }

        $this->deleteDirectory($this->runtimeRootPath(), $this->slug);
        $this->deleteFile($this->runtimePidPath(), $this->slug.'.pid');
        $this->deleteFile($this->runtimeLogPath(), $this->slug.'.log');
        $this->deleteFile($this->nginxConfigPath(), $this->slug.'.conf');
        $this->deleteFile($this->nginxEnabledPath(), $this->slug.'.conf');
    }

    private function runtimeRootPath(): string
    {
        return rtrim((string) config('platform.runtime.storage_path', storage_path('app/runtime-sites')), '/')
            .'/'.$this->slug;
    }

    private function runtimePidPath(): string
    {
        return rtrim((string) config('platform.runtime.pid_path', storage_path('app/runtime-pids')), '/')
            .'/'.$this->slug.'.pid';
    }

    private function runtimeLogPath(): string
    {
        return rtrim((string) config('platform.runtime.log_path', storage_path('logs/runtime-sites')), '/')
            .'/'.$this->slug.'.log';
    }

    private function nginxConfigPath(): string
    {
        return (string) ($this->nginx_conf_path ?: (rtrim((string) config('platform.nginx_sites_path'), '/').'/'.$this->slug.'.conf'));
    }

    private function nginxEnabledPath(): string
    {
        $enabledDir = str_replace(
            'sites-available',
            'sites-enabled',
            (string) config('platform.nginx_sites_path')
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
