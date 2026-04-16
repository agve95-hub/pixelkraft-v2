<?php

namespace Tests\Unit;

use App\Services\NginxConfigService;
use Tests\TestCase;

/**
 * Verify that the Nginx config injection guards in NginxConfigService
 * reject domain, slug, and deploy-path values that could inject directives
 * into the generated vhost config.
 *
 * All three validation methods are private, so tests invoke them via
 * reflection without touching the filesystem, DB, or nginx process.
 */
class NginxConfigInjectionTest extends TestCase
{
    private NginxConfigService $service;

    private \ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NginxConfigService::class);
        $this->reflection = new \ReflectionClass($this->service);
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true);

        return $m->invoke($this->service, ...$args);
    }

    // ── assertValidDomain ─────────────────────────────────────────────────

    /** @test */
    public function test_domain_newline_injection_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidDomain', "example.com\n  location / { return 200 'hacked'; }");
    }

    /** @test */
    public function test_domain_semicolon_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidDomain', 'example.com; allow all');
    }

    /** @test */
    public function test_domain_brace_injection_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidDomain', 'example.com } location / { deny all');
    }

    /** @test */
    public function test_empty_domain_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidDomain', '');
    }

    /** @test */
    public function test_domain_with_spaces_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidDomain', 'example.com evil.com');
    }

    /** @test */
    public function test_valid_domains_are_accepted(): void
    {
        // Should not throw
        $this->invoke('assertValidDomain', 'example.com');
        $this->invoke('assertValidDomain', 'sub.example.com');
        $this->invoke('assertValidDomain', '*.example.com');
        $this->invoke('assertValidDomain', '192.168.1.1'); // IPs allowed at this layer
        $this->assertTrue(true); // explicit assertion so the test isn't marked risky
    }

    // ── assertValidSlug ───────────────────────────────────────────────────

    /** @test */
    public function test_slug_with_path_traversal_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidSlug', '../evil-slug');
    }

    /** @test */
    public function test_slug_with_uppercase_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidSlug', 'My-Site');
    }

    /** @test */
    public function test_slug_with_special_characters_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidSlug', 'my_site');
    }

    /** @test */
    public function test_empty_slug_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidSlug', '');
    }

    /** @test */
    public function test_valid_slugs_are_accepted(): void
    {
        $this->invoke('assertValidSlug', 'my-site');
        $this->invoke('assertValidSlug', 'site123');
        $this->invoke('assertValidSlug', 'a');
        $this->assertTrue(true);
    }

    // ── assertValidDeployPath ─────────────────────────────────────────────

    /** @test */
    public function test_deploy_path_newline_injection_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidDeployPath', "/var/www/site\n  allow all;");
    }

    /** @test */
    public function test_deploy_path_semicolon_injection_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidDeployPath', '/var/www/site; deny all');
    }

    /** @test */
    public function test_deploy_path_brace_injection_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidDeployPath', '/var/www/site } location / { deny all');
    }

    /** @test */
    public function test_relative_deploy_path_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidDeployPath', 'relative/path');
    }

    /** @test */
    public function test_empty_deploy_path_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invoke('assertValidDeployPath', '');
    }

    /** @test */
    public function test_valid_deploy_paths_are_accepted(): void
    {
        $this->invoke('assertValidDeployPath', '/var/www/html');
        $this->invoke('assertValidDeployPath', '/srv/sites/my-site/dist');
        $this->invoke('assertValidDeployPath', '/home/deploy/pixelkraft-sites/my-site');
        $this->assertTrue(true);
    }

    // ── sanitizeRedirectToPath ────────────────────────────────────────────

    /** @test */
    public function test_sanitize_strips_injection_characters(): void
    {
        $result = $this->invoke('sanitizeRedirectToPath', "/target\n; deny all { }");
        $this->assertSame('/target deny all  ', $result);
    }

    /** @test */
    public function test_sanitize_preserves_normal_path(): void
    {
        $result = $this->invoke('sanitizeRedirectToPath', '/new/path');
        $this->assertSame('/new/path', $result);
    }
}
