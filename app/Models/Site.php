<?php

namespace App\Models;

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
        'name',
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
        'pre_deploy_hook',
        'post_deploy_hook',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'github_token'      => 'encrypted',
            'cf_api_token'      => 'encrypted',
            'smtp_password'     => 'encrypted',
            'ftp_ssh_password'  => 'encrypted',
            'env_variables'     => 'array',
            'monthly_retainer'  => 'decimal:2',
            'ssl_expires_at'    => 'datetime',
            'last_deployed_at'  => 'datetime',
            'last_synced_at'    => 'datetime',
            'is_active'         => 'boolean',
            'deploy_on_webhook' => 'boolean',
        ];
    }

    // ── Boot ────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            if (empty($site->slug)) {
                $site->slug = Str::slug($site->name);
            }
            if (empty($site->r2_bucket_prefix)) {
                $site->r2_bucket_prefix = 'sites/' . $site->slug . '/media';
            }
            if (empty($site->repo_path)) {
                $site->repo_path = config('pixelkraft.repos_path') . '/' . $site->slug;
            }
            if (empty($site->deploy_path)) {
                $site->deploy_path = config('pixelkraft.sites_deploy_path') . '/' . $site->slug;
            }
        });

        static::deleting(function (Site $site): void {
            $site->cleanupFilesystemArtifacts();
        });
    }

    // ── Helpers ──────────────────────────────────

    public function isLive(): bool
    {
        return $this->deploy_status === 'live';
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
            'repo'  => basename($path, '.git'),
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

        return strtolower($segments[0] . '/' . $segments[1]);
    }

    // ── Relationships ───────────────────────────

    public function pages()
    {
        return $this->hasMany(Page::class);
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

    public function uptimeChecks()
    {
        return $this->hasMany(UptimeCheck::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ── Computed ─────────────────────────────────

    public function latestDeploy()
    {
        return $this->hasOne(DeployLog::class)->latestOfMany();
    }

    public function latestUptimeCheck()
    {
        return $this->hasOne(UptimeCheck::class)->latestOfMany('checked_at');
    }

    private function cleanupFilesystemArtifacts(): void
    {
        $this->deleteDirectory($this->repo_path, $this->slug);
        $this->deleteDirectory($this->deploy_path, $this->slug);
        $this->deleteDirectory($this->runtimeRootPath(), $this->slug);
        $this->deleteFile($this->runtimePidPath(), $this->slug . '.pid');
        $this->deleteFile($this->runtimeLogPath(), $this->slug . '.log');
        $this->deleteFile($this->nginxConfigPath(), $this->slug . '.conf');
        $this->deleteFile($this->nginxEnabledPath(), $this->slug . '.conf');
    }

    private function runtimeRootPath(): string
    {
        return rtrim((string) config('pixelkraft.runtime.storage_path', storage_path('app/runtime-sites')), '/')
            . '/' . $this->slug;
    }

    private function runtimePidPath(): string
    {
        return rtrim((string) config('pixelkraft.runtime.pid_path', storage_path('app/runtime-pids')), '/')
            . '/' . $this->slug . '.pid';
    }

    private function runtimeLogPath(): string
    {
        return rtrim((string) config('pixelkraft.runtime.log_path', storage_path('logs/runtime-sites')), '/')
            . '/' . $this->slug . '.log';
    }

    private function nginxConfigPath(): string
    {
        return (string) ($this->nginx_conf_path ?: (rtrim((string) config('pixelkraft.nginx_sites_path'), '/') . '/' . $this->slug . '.conf'));
    }

    private function nginxEnabledPath(): string
    {
        $enabledDir = str_replace(
            'sites-available',
            'sites-enabled',
            (string) config('pixelkraft.nginx_sites_path')
        );

        return rtrim($enabledDir, '/') . '/' . $this->slug . '.conf';
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
