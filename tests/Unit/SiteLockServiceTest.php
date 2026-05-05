<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Services\SiteLockService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SiteLockServiceTest extends TestCase
{
    private SiteLockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SiteLockService;
    }

    private function makeSite(string $id = 'site-1'): Site
    {
        $site = new Site(['name' => 'Test', 'slug' => 'test']);
        $site->id = $id;

        return $site;
    }

    public function test_for_site_returns_lock_instance(): void
    {
        $site = $this->makeSite('lock-test-site');
        $lock = $this->service->forSite($site, 'repo', 30);

        $this->assertNotNull($lock);
    }

    public function test_for_site_creates_named_lock_with_site_id(): void
    {
        Cache::spy();

        $site = $this->makeSite('xyz-123');
        $this->service->forSite($site, 'repo', 60);

        Cache::shouldHaveReceived('lock')
            ->with('pixelkraft:site:xyz-123:lock:repo', 60)
            ->once();
    }

    public function test_block_executes_callback_and_returns_result(): void
    {
        $site = $this->makeSite('block-test');
        $result = $this->service->block($site, 'repo', fn () => 'done', 10, 5);

        $this->assertSame('done', $result);
    }

    public function test_block_with_different_resources_uses_different_lock_keys(): void
    {
        Cache::spy();

        $site = $this->makeSite('res-test');
        $this->service->forSite($site, 'repo', 30);
        $this->service->forSite($site, 'nginx', 30);

        Cache::shouldHaveReceived('lock')
            ->with('pixelkraft:site:res-test:lock:repo', 30)
            ->once();

        Cache::shouldHaveReceived('lock')
            ->with('pixelkraft:site:res-test:lock:nginx', 30)
            ->once();
    }

    public function test_block_throws_runtime_exception_on_lock_timeout(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Timed out waiting for site lock');

        $site = $this->makeSite('timeout-test');

        // Acquire the lock so the block call will time out
        $lock = Cache::lock('pixelkraft:site:timeout-test:lock:repo', 60);
        $lock->get();

        try {
            $this->service->block($site, 'repo', fn () => 'never', 5, 1);
        } finally {
            $lock->forceRelease();
        }
    }
}
