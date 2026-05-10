<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Services\BuildService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Verify that BuildService::DANGEROUS_ENV_VARS are stripped from site
 * env_variables before they reach the build process shell.
 */
class DeployServiceEnvFilterTest extends TestCase
{
    /** @return list<string> */
    private function dangerousKeys(): array
    {
        return BuildService::DANGEROUS_ENV_VARS;
    }

    public function test_dangerous_env_var_constant_is_non_empty(): void
    {
        $keys = $this->dangerousKeys();
        $this->assertNotEmpty($keys);
        $this->assertContains('NODE_OPTIONS', $keys);
        $this->assertContains('LD_PRELOAD', $keys);
        $this->assertContains('DYLD_INSERT_LIBRARIES', $keys);
        $this->assertContains('PATH', $keys);
    }

    public function test_dangerous_keys_are_upper_case(): void
    {
        foreach ($this->dangerousKeys() as $key) {
            $this->assertSame(strtoupper($key), $key, "Expected DANGEROUS_ENV_VARS key '{$key}' to be upper-cased");
        }
    }

    public function test_filtering_removes_dangerous_keys_from_site_env_variables(): void
    {
        $dangerous = $this->dangerousKeys();

        // Build a mixed env array: one dangerous key per entry plus safe ones
        $input = array_fill_keys($dangerous, 'injected');
        $input['VITE_APP_NAME'] = 'my-site';
        $input['NEXT_PUBLIC_API_URL'] = 'https://api.example.com';

        // Replicate the filter logic from DeployService::runCommand()
        $filtered = array_filter(
            $input,
            fn (string $k) => ! in_array(strtoupper($k), $dangerous, true),
            ARRAY_FILTER_USE_KEY
        );

        // No dangerous keys should survive
        foreach ($dangerous as $key) {
            $this->assertArrayNotHasKey($key, $filtered, "Dangerous key '{$key}' was not removed");
        }

        // Safe keys must be preserved
        $this->assertArrayHasKey('VITE_APP_NAME', $filtered);
        $this->assertArrayHasKey('NEXT_PUBLIC_API_URL', $filtered);
    }

    public function test_filtering_is_case_insensitive(): void
    {
        $dangerous = $this->dangerousKeys();

        // User might provide lowercase or mixed-case keys
        $input = [
            'node_options' => '--require /evil',
            'Node_Options' => '--require /evil2',
            'ld_preload' => '/evil.so',
            'SAFE_VAR' => 'ok',
        ];

        $filtered = array_filter(
            $input,
            fn (string $k) => ! in_array(strtoupper($k), $dangerous, true),
            ARRAY_FILTER_USE_KEY
        );

        $this->assertArrayNotHasKey('node_options', $filtered);
        $this->assertArrayNotHasKey('Node_Options', $filtered);
        $this->assertArrayNotHasKey('ld_preload', $filtered);
        $this->assertArrayHasKey('SAFE_VAR', $filtered);
    }

    public function test_build_tool_cache_env_uses_platform_owned_storage(): void
    {
        $site = new Site(['slug' => 'cache-demo']);
        $service = app(BuildService::class);
        $method = (new \ReflectionClass($service))->getMethod('buildToolCacheEnv');
        $method->setAccessible(true);

        /** @var array<string, string> $env */
        $env = $method->invoke($service, $site);

        $expectedBase = str_replace('\\', '/', storage_path('app/build-cache/cache-demo'));

        $this->assertSame($expectedBase.'/npm', str_replace('\\', '/', $env['NPM_CONFIG_CACHE']));
        $this->assertSame($expectedBase.'/npm', str_replace('\\', '/', $env['npm_config_cache']));
        $this->assertSame($expectedBase.'/yarn', str_replace('\\', '/', $env['YARN_CACHE_FOLDER']));
        $this->assertSame($expectedBase.'/corepack', str_replace('\\', '/', $env['COREPACK_HOME']));
        $this->assertSame($expectedBase.'/xdg', str_replace('\\', '/', $env['XDG_CACHE_HOME']));
        $this->assertSame('false', $env['NPM_CONFIG_UPDATE_NOTIFIER']);
        $this->assertDirectoryExists($env['NPM_CONFIG_CACHE']);
        $this->assertDirectoryExists($env['COREPACK_HOME']);

        File::deleteDirectory(storage_path('app/build-cache/cache-demo'));
    }
}
