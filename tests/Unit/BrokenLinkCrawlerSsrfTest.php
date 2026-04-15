<?php

namespace Tests\Unit;

use App\Services\BrokenLinkCrawler;
use Tests\TestCase;

/**
 * Verify that the SSRF guard in BrokenLinkCrawler::isPublicUrl() blocks
 * requests to private / loopback / link-local addresses and invalid schemes.
 *
 * isPublicUrl() is private, so tests use reflection to invoke it directly
 * rather than standing up a full crawl with filesystem and Site fixtures.
 */
class BrokenLinkCrawlerSsrfTest extends TestCase
{
    private \ReflectionMethod $isPublicUrl;

    private BrokenLinkCrawler $crawler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crawler = app(BrokenLinkCrawler::class);

        $reflection = new \ReflectionClass($this->crawler);
        $method = $reflection->getMethod('isPublicUrl');
        $method->setAccessible(true);
        $this->isPublicUrl = $method;
    }

    private function isPublic(string $url): bool
    {
        return $this->isPublicUrl->invoke($this->crawler, $url);
    }

    // ── Blocked: loopback ──────────────────────────────────────────────────

    /** @test */
    public function test_loopback_ipv4_is_blocked(): void
    {
        $this->assertFalse($this->isPublic('http://127.0.0.1/'));
        $this->assertFalse($this->isPublic('https://127.0.0.1/admin'));
        $this->assertFalse($this->isPublic('http://127.0.0.1:8080/secret'));
    }

    // ── Blocked: RFC 1918 private ranges ─────────────────────────────────

    /** @test */
    public function test_rfc1918_10_range_is_blocked(): void
    {
        $this->assertFalse($this->isPublic('http://10.0.0.1/'));
        $this->assertFalse($this->isPublic('http://10.255.255.255/'));
    }

    /** @test */
    public function test_rfc1918_172_16_range_is_blocked(): void
    {
        $this->assertFalse($this->isPublic('http://172.16.0.1/'));
        $this->assertFalse($this->isPublic('http://172.31.255.255/'));
    }

    /** @test */
    public function test_rfc1918_192_168_range_is_blocked(): void
    {
        $this->assertFalse($this->isPublic('http://192.168.0.1/'));
        $this->assertFalse($this->isPublic('http://192.168.255.255/'));
    }

    // ── Blocked: link-local (AWS metadata endpoint) ────────────────────────

    /** @test */
    public function test_link_local_169_254_range_is_blocked(): void
    {
        // 169.254.169.254 is the AWS instance-metadata endpoint —
        // blocking this specifically is critical on cloud infrastructure.
        $this->assertFalse($this->isPublic('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse($this->isPublic('http://169.254.0.1/'));
    }

    // ── Blocked: non-HTTP schemes ──────────────────────────────────────────

    /** @test */
    public function test_non_http_schemes_are_blocked(): void
    {
        $this->assertFalse($this->isPublic('file:///etc/passwd'));
        $this->assertFalse($this->isPublic('ftp://example.com/file.txt'));
        $this->assertFalse($this->isPublic('gopher://internal-host/'));
        $this->assertFalse($this->isPublic('dict://127.0.0.1:11211/'));
    }

    // ── Blocked: empty or malformed host ─────────────────────────────────

    /** @test */
    public function test_malformed_urls_are_blocked(): void
    {
        $this->assertFalse($this->isPublic('http:///no-host'));
        $this->assertFalse($this->isPublic('not-a-url'));
        $this->assertFalse($this->isPublic(''));
    }

    // ── Allowed: public IPs ─────────────────────────────────────────────

    /** @test */
    public function test_public_ip_is_allowed(): void
    {
        // 93.184.216.34 is the IANA-assigned IP for example.com —
        // a stable public IP that avoids a DNS lookup in the test.
        $this->assertTrue($this->isPublic('https://93.184.216.34/'));
        $this->assertTrue($this->isPublic('http://93.184.216.34/page.html'));
    }
}
