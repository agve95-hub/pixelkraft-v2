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
     * Upgrade an existing HTTP vhost to HTTPS using a Let's Encrypt certificate.
     *
     * Generates a redirect-to-HTTPS server block on port 80 and a TLS-enabled
     * block on port 443.  The certificate paths follow the standard certbot
     * convention: /etc/letsencrypt/live/{domain}/fullchain.pem and privkey.pem.
     *
     * Call after `certbot certonly --nginx -d {domain}` has succeeded.
     */
    public function generateSslConfig(Site $site): string
    {
        if (empty($site->domain) || empty($site->deploy_path)) {
            throw new \RuntimeException("Site [{$site->slug}] needs a domain and deploy path before an SSL config can be generated.");
        }

        $this->assertValidDomain((string) $site->domain);
        $this->assertValidSlug((string) $site->slug);
        $this->assertValidDeployPath((string) $site->deploy_path);

        $certPath = "/etc/letsencrypt/live/{$site->domain}";
        $redirects = $site->redirects()->where('is_active', true)->get();
        $redirectBlock = '';

        foreach ($redirects as $redirect) {
            $from = preg_quote($this->assertValidRedirectFromPath((string) $redirect->from_path), '~');
            $to = $this->sanitizeRedirectToPath($redirect->to_path);
            $flag = $this->redirectFlag((int) ($redirect->status_code ?? 301));
            $redirectBlock .= "    rewrite ^{$from}$ {$to} {$flag};\n";
        }

        $config = <<<NGINX
server {
    listen 80;
    server_name {$site->domain};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name {$site->domain};
    root {$site->deploy_path};
    index index.html index.htm;

    ssl_certificate     {$certPath}/fullchain.pem;
    ssl_certificate_key {$certPath}/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;

    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;

    # Redirects
{$redirectBlock}
    location ~* \.(css|js|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    location / {
        try_files \$uri \$uri/ \$uri.html /index.html =404;
    }

    location ~ /\. {
        deny all;
    }

    error_page 404 /404.html;

    access_log /var/log/nginx/{$site->slug}-access.log;
    error_log /var/log/nginx/{$site->slug}-error.log;
}
NGINX;

        $configPath = $this->getConfigPath($site);
        File::ensureDirectoryExists(dirname($configPath), 0755, true);
        File::put($configPath, $config);

        $site->update([
            'nginx_conf_path' => $configPath,
            'ssl_status' => 'active',
        ]);

        Log::info("Generated SSL Nginx config for [{$site->slug}] at [{$configPath}]");

        return $configPath;
    }

    /**
     * Remove Nginx config for a site.
     */
    public function removeConfig(Site $site): void
    {
        $configPath = $site->nginx_conf_path ?? $this->getConfigPath($site);

        // Reject paths outside the platform-owned directory before touching anything.
        $this->assertValidConfigPath($configPath);

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
     * Write or remove a maintenance override config for a site and reload Nginx.
     *
     * When maintenance mode is enabled a supplementary server block with higher
     * specificity intercepts all requests on the site's domain and returns a 503
     * with the configured maintenance page HTML written to disk.
     * When disabled the override config and its HTML are removed.
     */
    public function setMaintenanceMode(Site $site, bool $enabled): void
    {
        if (empty($site->domain)) {
            throw new \RuntimeException("Site [{$site->slug}] has no domain configured.");
        }

        $this->assertValidDomain((string) $site->domain);
        $this->assertValidSlug((string) $site->slug);

        $overridePath = $this->maintenanceConfigPath($site);
        $htmlPath = $this->maintenanceHtmlPath($site);
        $enabledOverridePath = str_replace('sites-available', 'sites-enabled', $overridePath);

        if ($enabled) {
            $settings = is_array($site->maintenance_settings) ? $site->maintenance_settings : [];
            $html = $this->renderMaintenanceHtml($settings);

            File::ensureDirectoryExists(dirname($htmlPath), 0755, true);
            File::put($htmlPath, $html);

            $config = $this->renderMaintenanceNginxConfig($site, $htmlPath);
            File::ensureDirectoryExists(dirname($overridePath), 0755, true);
            File::put($overridePath, $config);

            if (! File::exists($enabledOverridePath)) {
                symlink($overridePath, $enabledOverridePath);
            }
        } else {
            if (File::exists($enabledOverridePath)) {
                File::delete($enabledOverridePath);
            }
            if (File::exists($overridePath)) {
                File::delete($overridePath);
            }
            if (File::exists($htmlPath)) {
                File::delete($htmlPath);
            }
        }

        $this->reloadNginx();
    }

    private function maintenanceConfigPath(Site $site): string
    {
        return config('platform.nginx_sites_path')."/maintenance-{$site->slug}.conf";
    }

    private function maintenanceHtmlPath(Site $site): string
    {
        return storage_path("app/maintenance/{$site->slug}.html");
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function renderMaintenanceHtml(array $settings): string
    {
        $heading = e((string) ($settings['heading'] ?? "We'll be back soon"));
        $message = e((string) ($settings['message'] ?? 'Scheduled maintenance in progress.'));
        $bg = e((string) ($settings['bgColor'] ?? '#18181b'));
        $fg = e((string) ($settings['textColor'] ?? '#ffffff'));
        $accent = e((string) ($settings['accentColor'] ?? '#34d399'));

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Maintenance</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{align-items:center;background:{$bg};color:{$fg};display:flex;font-family:system-ui,-apple-system,sans-serif;height:100vh;justify-content:center;padding:24px;text-align:center}
  h1{font-size:2rem;margin-bottom:1rem}
  p{color:{$accent};font-size:1.125rem;max-width:520px;line-height:1.6}
</style>
</head>
<body>
  <div>
    <h1>{$heading}</h1>
    <p>{$message}</p>
  </div>
</body>
</html>
HTML;
    }

    private function renderMaintenanceNginxConfig(Site $site, string $htmlPath): string
    {
        $settings = is_array($site->maintenance_settings) ? $site->maintenance_settings : [];
        $allowedBlock = '';

        $allowedIps = array_filter(
            array_map('trim', explode(',', (string) ($settings['allowedIPs'] ?? $settings['allowedIps'] ?? ''))),
            fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP) !== false
        );

        foreach ($allowedIps as $ip) {
            $allowedBlock .= "    allow {$ip};\n";
        }

        if ($allowedBlock !== '') {
            $allowedBlock .= "    deny all;\n";
        }

        $htmlDir = dirname($htmlPath);
        $htmlFile = basename($htmlPath);

        return <<<NGINX
# platform maintenance override — auto-generated, do not edit.
server {
    listen 80;
    server_name {$site->domain};

{$allowedBlock}
    return 503;

    error_page 503 /{$htmlFile};
    location = /{$htmlFile} {
        root {$htmlDir};
        internal;
    }

    access_log /var/log/nginx/{$site->slug}-maintenance.log;
}
NGINX;
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
        $htpasswdPath = '/etc/nginx/.htpasswd-staging';

        return <<<NGINX
server {
    listen 80;
    server_name {$stagingDomain};
    root {$root};
    index index.html index.htm;

    # Protect staging previews with HTTP basic auth.
    # Create credentials with: htpasswd -c {$htpasswdPath} <username>
    auth_basic "Staging Preview";
    auth_basic_user_file {$htpasswdPath};

    location / {
        try_files \$uri \$uri/ \$uri.html /index.html =404;
    }

    location ~ /\. {
        deny all;
    }

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

    /**
     * Reject a `nginx_conf_path` value that sits outside the platform-owned
     * sites-available directory.  A site record whose nginx_conf_path points to
     * /etc/nginx/nginx.conf would let removeConfig() delete the server's main
     * config and shouldReloadNginx() would silently accept the invalid path.
     */
    private function assertValidConfigPath(string $path): void
    {
        $nginxRoot = rtrim((string) config('platform.nginx_sites_path'), '/');

        if (! str_starts_with($path, $nginxRoot.'/')) {
            throw new \InvalidArgumentException(
                "Config path [{$path}] is outside the platform-managed nginx directory [{$nginxRoot}]."
            );
        }
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
