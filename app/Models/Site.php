<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Site extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'repo_url',
        'branch',
        'github_token',
        'project_type',
        'deployment_mode',
        'build_command',
        'build_output_dir',
        'node_version',
        'env_variables',
        'domain',
        'ssl_status',
        'ssl_expires_at',
        'deploy_status',
        'last_deployed_at',
        'last_synced_at',
        'ga_property_id',
        'cf_zone_id',
        'gsc_property',
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
            'github_token'    => 'encrypted',
            'env_variables'   => 'array',
            'ssl_expires_at'  => 'datetime',
            'last_deployed_at' => 'datetime',
            'last_synced_at'  => 'datetime',
            'is_active'       => 'boolean',
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
}
