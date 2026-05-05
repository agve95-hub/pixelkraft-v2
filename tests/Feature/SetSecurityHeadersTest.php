<?php

namespace Tests\Feature;

use Tests\TestCase;

class SetSecurityHeadersTest extends TestCase
{
    private function loginPage(): \Illuminate\Testing\TestResponse
    {
        // /login is public and always rendered by the middleware stack
        return $this->get('/login');
    }

    public function test_x_frame_options_is_sameorigin(): void
    {
        $this->loginPage()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_x_content_type_options_is_nosniff(): void
    {
        $this->loginPage()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_referrer_policy_is_strict(): void
    {
        $this->loginPage()->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_permissions_policy_disables_sensors(): void
    {
        $response = $this->loginPage();
        $policy = $response->headers->get('Permissions-Policy');

        $this->assertNotNull($policy);
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertStringContainsString('payment=()', $policy);
    }

    public function test_content_security_policy_is_present(): void
    {
        $response = $this->loginPage();
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_csp_script_src_contains_nonce(): void
    {
        $response = $this->loginPage();
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\/=]{24}'/", $csp);
    }

    public function test_csp_does_not_contain_unsafe_inline_in_script_src(): void
    {
        $response = $this->loginPage();
        $csp = $response->headers->get('Content-Security-Policy');

        // Extract script-src directive only
        preg_match('/script-src\s+([^;]+)/', (string) $csp, $m);
        $scriptSrc = $m[1] ?? '';

        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
    }

    public function test_hsts_not_sent_over_plain_http(): void
    {
        // Default test requests are HTTP; HSTS should be absent
        $response = $this->loginPage();
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_hsts_sent_over_https(): void
    {
        // The full HTTP stack strips the HTTPS server var before Request::isSecure()
        // sees it, so we invoke the middleware directly with a request whose server
        // bag already has HTTPS=on — the only reliable way to hit that branch.
        $middleware = new \App\Http\Middleware\SetSecurityHeaders;
        $request = \Illuminate\Http\Request::create('/login', 'GET');
        $request->server->set('HTTPS', 'on');

        $response = $middleware->handle($request, fn ($r) => new \Illuminate\Http\Response('ok'));
        $hsts = $response->headers->get('Strict-Transport-Security');

        $this->assertNotNull($hsts);
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
    }

    public function test_nonce_is_unique_per_request(): void
    {
        $csp1 = $this->loginPage()->headers->get('Content-Security-Policy');
        $csp2 = $this->loginPage()->headers->get('Content-Security-Policy');

        preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", (string) $csp1, $m1);
        preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", (string) $csp2, $m2);

        $this->assertNotSame($m1[1] ?? '', $m2[1] ?? '');
    }
}
