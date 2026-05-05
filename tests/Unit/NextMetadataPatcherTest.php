<?php

namespace Tests\Unit;

use App\Services\NextMetadataPatcher;
use Tests\TestCase;

class NextMetadataPatcherTest extends TestCase
{
    private NextMetadataPatcher $patcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->patcher = new NextMetadataPatcher;
    }

    // ── canPatch ─────────────────────────────────

    public function test_can_patch_returns_true_when_export_const_metadata_present(): void
    {
        $content = "export const metadata = {\n  title: 'Hello',\n};\n";
        $this->assertTrue($this->patcher->canPatch($content));
    }

    public function test_can_patch_returns_false_when_no_metadata_export(): void
    {
        $this->assertFalse($this->patcher->canPatch("const title = 'Hello';\n"));
    }

    // ── patch — basic fields ─────────────────────

    public function test_patch_sets_title(): void
    {
        $content = "export const metadata = {\n};\n";
        $result = $this->patcher->patch($content, ['title' => 'My Page']);

        $this->assertStringContainsString("title: 'My Page'", $result);
    }

    public function test_patch_sets_description(): void
    {
        $content = "export const metadata = {\n};\n";
        $result = $this->patcher->patch($content, ['description' => 'A great page']);

        $this->assertStringContainsString("description: 'A great page'", $result);
    }

    public function test_patch_updates_existing_title(): void
    {
        $content = "export const metadata = {\n  title: 'Old Title',\n};\n";
        $result = $this->patcher->patch($content, ['title' => 'New Title']);

        $this->assertStringContainsString("title: 'New Title'", $result);
        $this->assertStringNotContainsString('Old Title', $result);
    }

    public function test_patch_removes_title_when_empty_string(): void
    {
        $content = "export const metadata = {\n  title: 'Old Title',\n};\n";
        $result = $this->patcher->patch($content, ['title' => '']);

        $this->assertStringNotContainsString('title:', $result);
    }

    public function test_patch_removes_title_when_null(): void
    {
        $content = "export const metadata = {\n  title: 'Old',\n};\n";
        $result = $this->patcher->patch($content, ['title' => null]);

        $this->assertStringNotContainsString('title:', $result);
    }

    // ── patch — nested fields ────────────────────

    public function test_patch_sets_og_title_in_open_graph(): void
    {
        $content = "export const metadata = {\n};\n";
        $result = $this->patcher->patch($content, ['og_title' => 'OG Title']);

        $this->assertStringContainsString('openGraph', $result);
        $this->assertStringContainsString("title: 'OG Title'", $result);
    }

    public function test_patch_sets_og_description(): void
    {
        $content = "export const metadata = {\n};\n";
        $result = $this->patcher->patch($content, ['og_description' => 'OG Desc']);

        $this->assertStringContainsString('openGraph', $result);
        $this->assertStringContainsString("description: 'OG Desc'", $result);
    }

    public function test_patch_sets_canonical_in_alternates(): void
    {
        $content = "export const metadata = {\n};\n";
        $result = $this->patcher->patch($content, ['canonical' => 'https://example.com/page']);

        $this->assertStringContainsString('alternates', $result);
        $this->assertStringContainsString("canonical: 'https://example.com/page'", $result);
    }

    public function test_patch_updates_existing_og_title(): void
    {
        $content = "export const metadata = {\n  openGraph: {\n    title: 'Old OG',\n  },\n};\n";
        $result = $this->patcher->patch($content, ['og_title' => 'New OG']);

        $this->assertStringContainsString("title: 'New OG'", $result);
        $this->assertStringNotContainsString('Old OG', $result);
    }

    // ── patch — throws on missing metadata ───────

    public function test_patch_throws_when_no_metadata_object(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('export const metadata');

        $this->patcher->patch("const x = 1;\n", ['title' => 'Foo']);
    }

    // ── patch — escaping ─────────────────────────

    public function test_patch_escapes_single_quotes_in_value(): void
    {
        $content = "export const metadata = {\n};\n";
        $result = $this->patcher->patch($content, ['title' => "It's alive"]);

        $this->assertStringContainsString("title: 'It\\'s alive'", $result);
    }

    // ── patch — content outside block preserved ──

    public function test_patch_preserves_content_outside_metadata(): void
    {
        $content = "import type { Metadata } from 'next';\n\nexport const metadata = {\n  title: 'Old',\n};\n\nexport default function Page() { return null; }\n";
        $result = $this->patcher->patch($content, ['title' => 'New']);

        $this->assertStringContainsString("import type { Metadata }", $result);
        $this->assertStringContainsString('export default function Page', $result);
    }

    // ── patch — keywords ─────────────────────────

    public function test_patch_sets_keywords(): void
    {
        $content = "export const metadata = {\n};\n";
        $result = $this->patcher->patch($content, ['keywords' => 'laravel, php, saas']);

        $this->assertStringContainsString("keywords: 'laravel, php, saas'", $result);
    }
}
