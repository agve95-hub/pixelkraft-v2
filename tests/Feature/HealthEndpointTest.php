<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_returns_ok_when_db_and_cache_are_available(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonStructure(['status', 'checks', 'timestamp'])
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok');
    }

    public function test_health_endpoint_is_accessible_without_authentication(): void
    {
        $this->getJson('/health')->assertOk();
    }
}
