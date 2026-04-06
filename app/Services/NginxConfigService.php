<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class NginxConfigService
{
    public function __construct(
        private SiteRuntimeService $runtime,
    ) {}

    /**
     * Generate and write an Nginx vhost config for a site.
     */
    public function generateConfig(Site $site): string
    {
        if (empty($site->domain) || empty($site->deploy_path)) {
            throw new \RuntimeException("Site [{$site->slug}] needs a domain and deploy path before Nginx config can be generated.");
        }

        $redirects = $site->redirects()->where('is_active', true)->get();

        $config = $this->renderTemplate($site, $redirects);

        $configPath = $this->getConfigPath($site);
        File::ensureDirectoryExists(dirname($configPath), 0755, true);
        File::put($configPath, $config);

        // Create symlink in sites-enabled
        $enabledPath = $this->getEnabledPath($site);

        if (! File::exists($enabledPath)) {
            symlink($configPath, $enabledPath);
        }

        $site->update(['nginx_conf_path' => $configPath]);

        Log::info("Generated Nginx config for [{$site->slug}] at [{$configPath}]");

        return $configPath;
    }

    /**
     * Remove Nginx config for a site.
     */
    public function removeConfig(Site $site): void
    {
        $configPath = $site->nginx_conf_path ?? $this->getConfigPath($site);
        $enabledPath = $this->getEnabledPath($site);

        if (File::exists($enabledPath)) {
            File::delete($enabledPath);
        }

        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        Log::info("Removed Nginx config for [{$site->slug}]");
    }

    /**
     * Test Nginx configuration before reloading.
     */
    public function testConfig(): bool
    {
        $result = Process::run('sudo nginx -t 2>&1');

        if (! $result->successful()) {
            Log::error('Nginx config test failed', ['output' => $result->output()]);
            return false;
        }

        return true;
    }

    /**
     * Reload Nginx to apply config changes.
     */
    public function reloadNginx(): void
    {
        if (! $this->testConfig()) {
            throw new \RuntimeException('Nginx config test failed. Config not reloaded.');
        }

        $result = Process::run('sudo systemctl reload nginx');

        if (! $result->successful()) {
            throw new \RuntimeException('Failed to reload Nginx: ' . $result->errorOutput());
        }

        Log::info('Nginx reloaded successfully.');
    }

    /**
     * Generate staging preview config (temporary subdomain).
     */
    public function generateStagingConfig(Site $site, string $stagingDomain): string
    {
        $stagingPath = "{$site->repo_path}/_staging_build";

        $config = $this->renderStagingTemplate($site, $stagingDomain, $stagingPath);

        $configPath = config('pixelkraft.nginx_sites_path') . "/staging-{$site->slug}.conf";
        File::put($configPath, $config);

        $enabledDir = str_replace('sites-available', 'sites-enabled', config('pixelkraft.nginx_sites_path'));
        $enabledPath = "{$enabledDir}/staging-{$site->slug}.conf";

        if (! File::exists($enabledPath)) {
            symlink($configPath, $enabledPath);
        }

        return $configPath;
    }

    /**
     * Remove staging config.
     */
    public function removeStagingConfig(Site $site): void
    {
        $configPath = config('pixelkraft.nginx_sites_path') . "/staging-{$site->slug}.conf";
        $enabledDir = str_replace('sites-available', 'sites-enabled', config('pixelkraft.nginx_sites_path'));
        $enabledPath = "{$enabledDir}/staging-{$site->slug}.conf";

        File::delete([$configPath, $enabledPath]);
    }

    // ── Private ─────────────────────────────────

    private function renderTemplate(Site $site, $redirects): string
    {
        $redirectBlock = '';

        foreach ($redirects as $redirect) {
            $from = preg_quote($redirect->from_path, '~');
            $redirectBlock .= "    rewrite ^{$from}$ {$redirect->to_path} permanent;\n";
        }

        if ($this->runtime->usesRuntimeServer($site)) {
            return $this->renderRuntimeTemplate($site, $redirectBlock);
        }

        $webpBlock = <<<'NGINX'
    # WebP auto-serve
    location ~* \.(png|jpe?g)$ {
        add_header Vary Accept;
        try_files $uri$webp_suffix $uri =404;
    }
NGINX;

        return <<<NGINX
server {
    listen 80;
    server_name {$site->domain};
    root {$site->deploy_path};
    index index.html index.htm;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;

    # WebP support
    set \$webp_suffix "";
    if (\$http_accept ~* "webp") {
        set \$webp_suffix ".webp";
    }

{$webpBlock}

    # Redirects
{$redirectBlock}

    # Static file caching
    location ~* \.(css|js|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location ~* \.(png|jpe?g|webp|avif)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # SPA fallback / clean URLs
    location / {
        try_files \$uri \$uri/ \$uri.html /index.html =404;
    }

    # Deny hidden files
    location ~ /\. {
        deny all;
    }

    error_page 404 /404.html;

    access_log /var/log/nginx/{$site->slug}-access.log;
    error_log /var/log/nginx/{$site->slug}-error.log;
}
NGINX;
    }

    private function renderRuntimeTemplate(Site $site, string $redirectBlock): string
    {
        $host = config('pixelkraft.runtime.host', '127.0.0.1');
        $port = $this->runtime->portFor($site);

        return <<<NGINX
server {
    listen 80;
    server_name {$site->domain};

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;

    # Redirects
{$redirectBlock}

    location / {
        proxy_pass http://{$host}:{$port};
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 60s;
    }

    location ~ /\. {
        deny all;
    }

    access_log /var/log/nginx/{$site->slug}-access.log;
    error_log /var/log/nginx/{$site->slug}-error.log;
}
NGINX;
    }

    private function renderStagingTemplate(Site $site, string $stagingDomain, string $root): string
    {
        return <<<NGINX
server {
    listen 80;
    server_name {$stagingDomain};
    root {$root};
    index index.html index.htm;

    location / {
        try_files \$uri \$uri/ \$uri.html /index.html =404;
    }

    # Basic auth for staging
    # auth_basic "Staging Preview";
    # auth_basic_user_file /etc/nginx/.htpasswd;

    access_log /var/log/nginx/staging-{$site->slug}-access.log;
    error_log /var/log/nginx/staging-{$site->slug}-error.log;
}
NGINX;
    }

    private function getConfigPath(Site $site): string
    {
        return config('pixelkraft.nginx_sites_path') . "/{$site->slug}.conf";
    }

    private function getEnabledPath(Site $site): string
    {
        $enabledDir = str_replace('sites-available', 'sites-enabled', config('pixelkraft.nginx_sites_path'));

        return "{$enabledDir}/{$site->slug}.conf";
    }
}
