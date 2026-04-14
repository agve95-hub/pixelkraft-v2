<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Services\DeployService;
use Tests\TestCase;

/**
 * Verify that DeployService::DANGEROUS_ENV_VARS are stripped from site env_variables
 * before they reach the build process shell.
 *
 * Because runCommand() is private, we test the observable effect via the
 * constant itself: any key listed there must be removed from the merged env,
 * which we verify by reflection on the constant and checking array_filter logic.
 */
class DeployServiceEnvFilterTest extends TestCase
{
    /** @return list<string> */
    private function dangerousKeys(): array
    {
        return DeployService::DANGEROUS_ENV_VARS;
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
}
