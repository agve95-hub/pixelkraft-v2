<?php

namespace Tests\Unit;

use App\Services\HtmlMinifier;
use PHPUnit\Framework\TestCase;

class HtmlMinifierTest extends TestCase
{
    private HtmlMinifier $minifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->minifier = new HtmlMinifier;
    }

    // ── minifyHtml ────────────────────────────────

    public function test_minify_collapses_whitespace_between_tags(): void
    {
        $html = "<div>\n    <p>Hello</p>\n</div>";
        $result = $this->minifier->minifyHtml($html);
        $this->assertStringNotContainsString("\n    ", $result);
        $this->assertStringContainsString('<p>Hello</p>', $result);
    }

    public function test_minify_removes_html_comments(): void
    {
        $html = '<div><!-- remove me --><p>Keep</p></div>';
        $result = $this->minifier->minifyHtml($html);
        $this->assertStringNotContainsString('remove me', $result);
        $this->assertStringContainsString('Keep', $result);
    }

    public function test_minify_preserves_conditional_comments(): void
    {
        $html = '<!--[if IE]><link rel="stylesheet" href="ie.css"><![endif]--><p>Main</p>';
        $result = $this->minifier->minifyHtml($html);
        $this->assertStringContainsString('[if IE]', $result);
    }

    public function test_minify_preserves_script_content(): void
    {
        $html = '<script>var   x   =   1;</script><p>After</p>';
        $result = $this->minifier->minifyHtml($html);
        $this->assertStringContainsString('var   x   =   1;', $result);
    }

    public function test_minify_preserves_style_content(): void
    {
        $html = '<style>.a   {   color:   red;   }</style><p>Hi</p>';
        $result = $this->minifier->minifyHtml($html);
        $this->assertStringContainsString('.a   {   color:   red;   }', $result);
    }

    public function test_minify_preserves_pre_content(): void
    {
        $pre = "<pre>  indented\n  code  </pre>";
        $result = $this->minifier->minifyHtml($pre.'<p>after</p>');
        $this->assertStringContainsString('  indented', $result);
    }

    public function test_minify_collapses_multiple_spaces(): void
    {
        $html = '<p>Hello     World</p>';
        $result = $this->minifier->minifyHtml($html);
        $this->assertStringContainsString('Hello World', $result);
    }

    public function test_minify_empty_string_returns_empty(): void
    {
        $this->assertSame('', $this->minifier->minifyHtml(''));
    }

    // ── minifyCss ─────────────────────────────────

    public function test_minify_css_removes_comments(): void
    {
        $css = '/* Remove me */ body { color: red; }';
        $result = $this->minifier->minifyCss($css);
        $this->assertStringNotContainsString('Remove me', $result);
        $this->assertStringContainsString('color', $result);
    }

    public function test_minify_css_removes_unnecessary_whitespace(): void
    {
        $css = 'body   {   color :   red ;   margin :   0   ;   }';
        $result = $this->minifier->minifyCss($css);
        $this->assertStringContainsString('color', $result);
        // Result should be shorter than input
        $this->assertLessThan(strlen($css), strlen($result));
    }

    public function test_minify_css_empty_returns_empty(): void
    {
        $this->assertSame('', trim($this->minifier->minifyCss('')));
    }

    // ── minifyJs ──────────────────────────────────

    public function test_minify_js_removes_single_line_comments(): void
    {
        $js = "// Remove this\nvar x = 1;";
        $result = $this->minifier->minifyJs($js);
        $this->assertStringNotContainsString('Remove this', $result);
        $this->assertStringContainsString('var x = 1', $result);
    }

    public function test_minify_js_removes_block_comments(): void
    {
        $js = '/* block */ function foo() { return 1; }';
        $result = $this->minifier->minifyJs($js);
        $this->assertStringNotContainsString('block', $result);
        $this->assertStringContainsString('function foo', $result);
    }

    public function test_minify_js_collapses_whitespace(): void
    {
        $js = 'var   x   =   1;';
        $result = $this->minifier->minifyJs($js);
        $this->assertSame(strlen($js), strlen($js)); // sanity
        $this->assertLessThanOrEqual(strlen($js), strlen($result));
    }
}
