<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SslService
{
    /**
     * Provision a new SSL certificate for a site using Certbot.
     */
    public function provision(Site $site): bool
    {
        if (empty($site->domain)) {
            throw new \RuntimeException("Site [{$site->slug}] has no domain configured.");
        }

        Log::info("Provisioning SSL for [{$site->domain}]");

        $result = Process::timeout(120)->run(
            'sudo certbot --nginx -d ' . escapeshellarg($site->domain) .
            ' --non-interactive --agree-tos --email ' . escapeshellarg(config('mail.from.address', 'admin@localhost')) .
            ' --redirect'
        );

        if (! $result->successful()) {
            Log::error("SSL provisioning failed for [{$site->domain}]", [
                'output' => $result->output(),
                'error'  => $result->errorOutput(),
            ]);

            $site->update(['ssl_status' => 'error']);

            Notification::createAlert(
                type: 'ssl_expiring',
                title: "SSL provisioning failed: {$site->domain}",
                body: $result->errorOutput(),
                siteId: $site->id,
            );

            return false;
        }

        // Read certificate expiry
        $expiresAt = $this->getCertExpiry($site->domain);

        $site->update([
            'ssl_status'     => 'active',
            'ssl_expires_at' => $expiresAt,
        ]);

        Log::info("SSL provisioned for [{$site->domain}]", [
            'expires_at' => $expiresAt?->toIso8601String(),
        ]);

        return true;
    }

    /**
     * Check SSL certificate expiry date for a domain.
     */
    public function getCertExpiry(string $domain): ?Carbon
    {
        $result = Process::timeout(10)->run(
            'sudo certbot certificates --domain ' . escapeshellarg($domain) . ' 2>/dev/null'
        );

        if (preg_match('/Expiry Date:\s+(.+?)\s+\(/', $result->output(), $match)) {
            try {
                return Carbon::parse($match[1]);
            } catch (\Throwable $e) {
                return null;
            }
        }

        // Fallback: check via openssl
        $result = Process::timeout(10)->run(
            'echo | openssl s_client -servername ' . escapeshellarg($domain) .
            ' -connect ' . escapeshellarg($domain) . ':443 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null'
        );

        if (preg_match('/notAfter=(.+)/', $result->output(), $match)) {
            try {
                return Carbon::parse($match[1]);
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Check all sites' SSL expiry and alert if expiring soon.
     */
    public function checkAllCertificates(): int
    {
        $sites = Site::query()
            ->where('is_active', true)
            ->whereNotNull('domain')
            ->where('ssl_status', 'active')
            ->get();

        $alertCount = 0;

        foreach ($sites as $site) {
            $expiresAt = $this->getCertExpiry($site->domain);

            if (! $expiresAt) {
                continue;
            }

            $site->update(['ssl_expires_at' => $expiresAt]);

            // Alert if expiring within 14 days
            if ($expiresAt->diffInDays(now()) <= 14) {
                $alertCount++;

                Notification::createAlert(
                    type: 'ssl_expiring',
                    title: "SSL expiring: {$site->domain}",
                    body: "Certificate expires {$expiresAt->diffForHumans()}.",
                    siteId: $site->id,
                );

                Log::warning("SSL expiring soon for [{$site->domain}]", [
                    'expires_at' => $expiresAt->toIso8601String(),
                ]);
            }

            // Mark as expired
            if ($expiresAt->isPast()) {
                $site->update(['ssl_status' => 'expired']);
            }
        }

        return $alertCount;
    }

    /**
     * Renew all certificates (certbot handles this natively, but we track status).
     */
    public function renewAll(): bool
    {
        $result = Process::timeout(300)->run('sudo certbot renew --quiet');

        return $result->successful();
    }
}
