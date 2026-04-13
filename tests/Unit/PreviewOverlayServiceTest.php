<?php

namespace Tests\Unit;

use App\Models\EditableRegion;
use App\Models\Page;
use App\Models\Site;
use App\Services\PreviewOverlayService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PHPUnit\Framework\TestCase;

class PreviewOverlayServiceTest extends TestCase
{
    public function test_it_injects_temporary_preview_attributes_for_matched_regions(): void
    {
        $site = new Site(['id' => 'site-1', 'name' => 'Demo Site']);
        $site->id = 'site-1';

        $page = new Page(['id' => 'page-1', 'url_path' => '/']);
        $page->id = 'page-1';

        $region = new EditableRegion([
            'id' => 'region-hero',
            'selector' => 'h1',
            'render_selector' => 'h1',
            'region_type' => 'text',
            'current_content' => 'Hero title',
            'confidence_score' => 0.95,
        ]);
        $region->id = 'region-hero';

        $page->setRelation('editableRegions', new EloquentCollection([$region]));

        $html = <<<'HTML'
<html>
<head><title>Demo</title></head>
<body><main><h1>Hero title</h1><p>Body copy</p></main></body>
</html>
HTML;

        $decorated = (new PreviewOverlayService())->decorate($site, $page, $html);

        $this->assertStringContainsString('data-pk-preview="editor"', $decorated);
        $this->assertStringContainsString('data-pk-region-id="region-hero"', $decorated);
        $this->assertStringContainsString('data-pk-node-id="pk-node-region-hero-1"', $decorated);
        $this->assertStringContainsString('name="pixelkraft-preview"', $decorated);
    }

    public function test_it_falls_back_to_text_matching_when_selector_no_longer_matches(): void
    {
        $site = new Site(['id' => 'site-2', 'name' => 'Fallback Site']);
        $site->id = 'site-2';

        $page = new Page(['id' => 'page-2', 'url_path' => '/about']);
        $page->id = 'page-2';

        $region = new EditableRegion([
            'id' => 'region-copy',
            'selector' => '.missing-selector',
            'render_selector' => '.missing-selector',
            'region_type' => 'text',
            'current_content' => 'Built with careful craftsmanship.',
            'confidence_score' => 0.91,
            'dom_fingerprint' => ['tag' => 'p'],
        ]);
        $region->id = 'region-copy';

        $page->setRelation('editableRegions', new EloquentCollection([$region]));

        $html = <<<'HTML'
<html>
<head><title>About</title></head>
<body><main><section><p>Built with careful craftsmanship.</p></section></main></body>
</html>
HTML;

        $decorated = (new PreviewOverlayService())->decorate($site, $page, $html);

        $this->assertStringContainsString('data-pk-region-id="region-copy"', $decorated);
        $this->assertStringContainsString('data-pk-region-type="text"', $decorated);
    }
}
