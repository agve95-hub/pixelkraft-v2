<?php

namespace Tests\Unit;

use App\Models\EditableRegion;
use App\Models\Page;
use App\Models\Site;
use App\Services\ContentPatcher;
use App\Services\SiteSupportService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ContentPatcherPathTraversalTest extends TestCase
{
    private string $tmpRepo;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an isolated temp repo directory with a legitimate file inside
        $this->tmpRepo = sys_get_temp_dir().'/pk_patcher_test_'.uniqid('', true);
        mkdir($this->tmpRepo, 0755, true);
        file_put_contents($this->tmpRepo.'/index.html', '<h1>hello</h1>');

        // Also create a sibling directory to try to escape into
        mkdir(dirname($this->tmpRepo).'/outside_repo', 0755, true);
        file_put_contents(dirname($this->tmpRepo).'/outside_repo/secret.txt', 'SENSITIVE');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmpRepo);
        File::deleteDirectory(dirname($this->tmpRepo).'/outside_repo');
        parent::tearDown();
    }

    private function makeSite(): Site
    {
        $site = new Site;
        $site->repo_path = $this->tmpRepo;
        $site->project_type = 'static_html';

        return $site;
    }

    private function makePage(Site $site): Page
    {
        $page = new Page;
        $page->file_path = 'index.html';
        // Allow direct property setting without model booting
        $page->setRelation('site', $site);

        return $page;
    }

    private function makeRegion(Page $page, array $sourceLocation): EditableRegion
    {
        $region = new EditableRegion;
        $region->source_location = $sourceLocation;
        $region->selector = 'h1';
        $region->region_type = 'text';
        $region->current_content = 'hello';
        $region->is_static = false;
        $region->setRelation('page', $page);

        return $region;
    }

    public function test_canVisuallyEditRegion_returns_false_for_traversal_path(): void
    {
        // Mock SiteSupportService so it doesn't abort on unhydrated models
        $this->mock(SiteSupportService::class, function ($mock) {
            $mock->shouldReceive('supportsVisualEditing')->andReturn(true);
        });

        $site = $this->makeSite();
        $page = $this->makePage($site);

        $region = $this->makeRegion($page, [
            'file' => '../../outside_repo/secret.txt',
            'source_type' => 'html',
        ]);

        $patcher = app(ContentPatcher::class);
        $this->assertFalse($patcher->canVisuallyEditRegion($region));
    }

    public function test_canVisuallyEditRegion_returns_true_for_legitimate_path(): void
    {
        $this->mock(SiteSupportService::class, function ($mock) {
            $mock->shouldReceive('supportsVisualEditing')->andReturn(true);
        });

        $site = $this->makeSite();
        $page = $this->makePage($site);

        $region = $this->makeRegion($page, [
            'file' => 'index.html',
            'source_type' => 'html',
        ]);

        $patcher = app(ContentPatcher::class);
        $this->assertTrue($patcher->canVisuallyEditRegion($region));
    }

    public function test_applyEdit_throws_for_traversal_path(): void
    {
        $this->mock(SiteSupportService::class, function ($mock) {
            $mock->shouldReceive('supportsVisualEditing')->andReturn(true);
        });

        $site = $this->makeSite();
        $page = $this->makePage($site);

        $region = $this->makeRegion($page, [
            'file' => '../../outside_repo/secret.txt',
            'source_type' => 'html',
        ]);

        $patcher = app(ContentPatcher::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/outside.*repository/i');

        $patcher->applyEdit($region, 'pwned');

        // Verify the file was not overwritten
        $this->assertSame('SENSITIVE', file_get_contents(dirname($this->tmpRepo).'/outside_repo/secret.txt'));
    }
}
