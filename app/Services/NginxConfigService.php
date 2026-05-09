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

        $this->assertValidDomain((string) $site->domain);
        $this->assertValidSlug((string) $site->slug);
        $this->assertValidDeployPath((string) $site->deploy_path);

        $redirects = $site->redirects()->where('is_active', true)->get();

        $config = $this->renderTemplate($site, $redirects);

        $configPath = $this->getConfigPath($site);
        File::ensureDirectoryExists(dirname($configPath), 0755, true);
        File::put($configPath, $config);

        // Create symlink in sites-enabled
        $enabledPath = $this->getEnabledPath($site);
        File::ensureDirectoryExists(dirname($enabledPath), 0755, true);

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
            throw new \RuntimeException('Failed to reload Nginx: '.$result->errorOutput());
        }

        Log::info('Nginx reloaded successfully.');
    }

    /**
     * Generate staging preview config (temporary subdomain).
     */
    public function generateStagingConfig(Site $site, string $stagingDomain): string
    {
        $this->assertValidDomain($stagingDomain);
        $this->assertValidSlug((string) $site->slug);

        $stagingPath = "{$site->repo_path}/_staging_build";

        $config = $this->renderStagingTemplate($site, $stagingDomain, $stagingPath);

        $configPath = config('platform.nginx_sites_path')."/staging-{$site->slug}.conf";
        File::ensureDirectoryExists(dirname($configPath), 0755, true);
        File::put($configPath, $config);

        $enabledDir = str_replace('sites-available', 'sites-enabled', config('platform.nginx_sites_path'));
        File::ensureDirectoryExists($enabledDir, 0755, true);
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
        $configPath = config('platform.nginx_sites_path')."/staging-{$site->slug}.conf";
        $enabledDir = str_replace('sites-available', 'sites-enabled', config('platform.nginx_sites_path'));
        $enabledPath = "{$enabledDir}/staging-{$site->slug}.conf";

        File::delete([$configPath, $enabledPath]);
    }

    // ── Private ─────────────────────────────────

    private function renderTemplate(Site $site, iterable $redirects): string
    {
        $redirectBlock = '';

        foreach ($redirects as $redirect) {
            $from = preg_quote($this->assertValidRedirectFromPath((string) $redirect->from_path), '~');
            $to = $this->sanitizeRedirectToPath($redirect->to_path);
            $flag = $this->redirectFlag((int) ($redirect->status_code ?? 301));
            $redirectBlock .= "    rewrite ^{$from}$ {$to} {$flag};\n";
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
        $host = config('platform.runtime.host', '127.0.0.1');
        $port = $this->runtime->effectivePortFor($site);

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
        return config('platform.nginx_sites_path')."/{$site->slug}.conf";
    }

    private function getEnabledPath(Site $site): string
    {
        $enabledDir = str_replace('sites-available', 'sites-enabled', config('platform.nginx_sites_path'));

        return "{$enabledDir}/{$site->slug}.conf";
    }

    // ── Input validation ────────────────────────────────────────────────────

    /**
     * Assert that a domain value is safe to embed in an Nginx config.
     * Rejects newlines and control characters that could inject additional directives.
     */
    private function assertValidDomain(string $domain): void
    {
        if ($domain === '') {
            throw new \InvalidArgumentException('Domain must not be empty.');
        }

        // Reject newlines, semicolons, braces – the primary Nginx injection chars.
        if (preg_match('/[\r\n\t;{}\\\\]/', $domain)) {
            throw new \InvalidArgumentException(
                "Domain [{$domain}] contains characters not allowed in an Nginx config."
            );
        }

        // Allow hostnames, sub-domains, and bare IPv4 addresses.
        // The regex is intentionally lenient (e.g. allows *.example.com for wildcard
        // entries) but rejects anything that looks like injected directives.
        if (! preg_match('/^[a-zA-Z0-9*][a-zA-Z0-9.\-*]*$/', $domain)) {
            throw new \InvalidArgumentException(
                "Domain [{$domain}] is not a valid hostname or IP address."
            );
        }
    }

    /**
     * Assert that a site slug is safe to use in Nginx config file names and
     * log-path values. Slugs are generated by Laravel's Str::slug(), but a
     * direct DB update could inject unexpected characters.
     */
    private function assertValidSlug(string $slug): void
    {
        if ($slug === '' || ! preg_match('/^[a-z0-9][a-z0-9\-]*$/', $slug)) {
            throw new \InvalidArgumentException(
                "Site slug [{$slug}] must contain only lowercase letters, digits, and hyphens."
            );
        }
    }

    /**
     * Assert that a deploy path is safe to embed as a 'root' directive.
     * Rejects newlines and characters that could break the Nginx directive syntax.
     */
    private function assertValidDeployPath(string $path): void
    {
        if ($path === '') {
            throw new \InvalidArgumentException('Deploy path must not be empty.');
        }

        if (preg_match('/[\r\n\t;{}]/', $path)) {
            throw new \InvalidArgumentException(
                'Deploy path contains characters not allowed in an Nginx config.'
            );
        }

        if (! str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('Deploy path must be an absolute path starting with /.');
        }
    }

    /**
     * Remove characters from a redirect destination path that could inject
     * Nginx directives (newlines, semicolons, braces).
     */
    private function sanitizeRedirectToPath(string $path): string
    {
        $sanitized = preg_replace('/[\r\n\t;{}]/', '', $path) ?? $path;

        return trim($sanitized) !== '' ? $sanitized : '/';
    }

    private function assertValidRedirectFromPath(string $path): string
    {
        if ($path === '' || ! str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('Redirect source path must start with /.');
        }

        if (preg_match('/[\r\n\t;{}]/', $path)) {
            throw new \InvalidArgumentException('Redirect source path contains characters not allowed in an Nginx config.');
        }

        return $path;
    }

    private function redirectFlag(int $statusCode): string
    {
        return $statusCode === 302 ? 'redirect' : 'permanent';
    }
}
