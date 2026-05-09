<?php

namespace Tests\Unit;

use App\Rules\PublicUrl;
use Tests\TestCase;

class PublicUrlRuleTest extends TestCase
{
    private function passes(string $url): bool
    {
        $failed = false;
        (new PublicUrl)->validate('url', $url, function () use (&$failed) {
            $failed = true;
        });

        return ! $failed;
    }

    public function test_it_rejects_non_http_schemes(): void
    {
        $this->assertFalse($this->passes('ftp://example.com/file'));
        $this->assertFalse($this->passes('file:///etc/passwd'));
        $this->assertFalse($this->passes('gopher://example.com'));
    }

    public function test_it_rejects_localhost(): void
    {
        $this->assertFalse($this->passes('http://localhost/'));
        $this->assertFalse($this->passes('http://localhost:8080/health'));
    }

    public function test_it_rejects_loopback_ip(): void
    {
        $this->assertFalse($this->passes('http://127.0.0.1/'));
        $this->assertFalse($this->passes('http://127.0.0.1:3000/health'));
    }

    public function test_it_rejects_rfc1918_private_ranges(): void
    {
        $this->assertFalse($this->passes('http://192.168.1.1/'));
        $this->assertFalse($this->passes('http://10.0.0.1/'));
        $this->assertFalse($this->passes('http://172.16.0.1/'));
        $this->assertFalse($this->passes('http://172.31.255.255/'));
    }

    public function test_it_rejects_link_local_range(): void
    {
        // 169.254.0.0/16 — includes AWS instance metadata endpoint
        $this->assertFalse($this->passes('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse($this->passes('http://169.254.0.1/'));
    }

    public function test_it_accepts_public_https_url(): void
    {
        // We can't guarantee DNS in all CI environments, so skip if DNS fails.
        // The rule itself is tested for the private-IP logic via direct IP tests above.
        $url = 'https://example.com/health';
        $resolved = gethostbyname('example.com');
        if ($resolved === 'example.com') {
            $this->markTestSkipped('DNS not available in this environment.');
        }

        $this->assertTrue($this->passes($url));
    }

    public function test_it_accepts_public_http_url(): void
    {
        $url = 'http://93.184.216.34/'; // example.com IP — public

        // Directly pass an IP URL — no DNS needed, and this is a known public IP.
        // Note: filter_var with NO_PRIV_RANGE | NO_RES_RANGE accepts 93.184.216.34.
        $this->assertTrue($this->passes($url));
    }
}
