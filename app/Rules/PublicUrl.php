<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a URL:
 *  - uses the http or https scheme only
 *  - resolves to a publicly routable IP address
 *
 * Blocked ranges (RFC 5735 / RFC 4193 / RFC 3927 / RFC 1918):
 *  - 127.0.0.0/8      (loopback)
 *  - 10.0.0.0/8       (private)
 *  - 172.16.0.0/12    (private)
 *  - 192.168.0.0/16   (private)
 *  - 169.254.0.0/16   (link-local / AWS metadata endpoint)
 *  - ::1              (IPv6 loopback)
 *  - fc00::/7         (IPv6 unique-local)
 *  - fe80::/10        (IPv6 link-local)
 *  - 0.0.0.0/8        (unspecified)
 *  - 224.0.0.0/4      (multicast)
 *  - 240.0.0.0/4      (reserved)
 *  - localhost         (hostname check)
 */
class PublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = (string) $value;

        // Only allow http:// and https://
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must use the http or https scheme.');

            return;
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            $fail('The :attribute must contain a valid hostname.');

            return;
        }

        // Reject explicit "localhost" variants regardless of DNS resolution.
        if (in_array(strtolower($host), ['localhost', 'ip6-localhost', 'ip6-loopback'], true)) {
            $fail('The :attribute must not target a loopback address.');

            return;
        }

        // Determine the IP to validate against private/reserved ranges.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Host is already a raw IP address — validate it directly.
            $ip = $host;
        } else {
            // Resolve hostname to IP. gethostbyname() returns the input unchanged on failure.
            $resolved = gethostbyname($host);

            if ($resolved === $host) {
                // Could not resolve — fail closed rather than allow unknown destinations.
                $fail('The :attribute hostname could not be resolved.');

                return;
            }

            $ip = $resolved;
        }

        // Reject private / reserved / non-public IP ranges.
        if (! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            $fail('The :attribute must not point to a private or reserved IP address.');

            return;
        }
    }
}
