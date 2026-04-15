<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a repository URL belongs to a known, trusted git host.
 *
 * Accepted formats:
 *   HTTPS  — https://github.com/owner/repo(.git)?
 *            https://gitlab.com/owner/repo(.git)?
 *            https://bitbucket.org/owner/repo(.git)?
 *   SSH    — git@github.com:owner/repo.git
 *            git@gitlab.com:owner/repo.git
 *            git@bitbucket.org:owner/repo.git
 *
 * Rejected:
 *   - file:// or any non-http/https/git@ scheme
 *   - Private/internal hostnames (localhost, 127.0.0.1, 10.x, 192.168.x, etc.)
 *   - Unknown external hosts that would receive the stored GitHub token
 *
 * Additional hosts can be allowed via the ALLOWED_GIT_HOSTS env variable
 * (comma-separated, e.g. "github.mycompany.com,git.example.com"), read
 * through config('pixelkraft.allowed_git_hosts') so it works with cached config.
 */
class GitRemoteUrl implements ValidationRule
{
    /** @var list<string> */
    private array $allowedHosts;

    public function __construct()
    {
        $defaults = ['github.com', 'gitlab.com', 'bitbucket.org'];
        $extra = config('pixelkraft.allowed_git_hosts', []);

        $this->allowedHosts = array_values(array_unique(array_merge($defaults, $extra)));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = trim((string) $value);

        // Accept SSH format: git@host:owner/repo.git
        if (str_starts_with($url, 'git@')) {
            if (! preg_match('/^git@([^:]+):(.+)$/', $url, $m)) {
                $fail('The :attribute must be a valid git remote URL (HTTPS or SSH).');

                return;
            }

            $host = strtolower($m[1]);

            if (! $this->isAllowedHost($host)) {
                $fail("The :attribute host '{$host}' is not in the list of allowed git providers.");

                return;
            }

            return;
        }

        // Accept HTTPS format only — no http://, file://, etc.
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'https') {
            $fail('The :attribute must use the https scheme or SSH (git@host:owner/repo) format.');

            return;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            $fail('The :attribute must contain a valid hostname.');

            return;
        }

        if (! $this->isAllowedHost($host)) {
            $fail("The :attribute host '{$host}' is not in the list of allowed git providers.");

            return;
        }

        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '' || ! preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._/-]+$#', $path)) {
            $fail('The :attribute must include a valid owner/repo path (e.g. https://github.com/owner/repo).');

            return;
        }
    }

    private function isAllowedHost(string $host): bool
    {
        foreach ($this->allowedHosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
