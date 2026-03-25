<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class HtmlMinifier
{
    /**
     * Minify all HTML/CSS/JS files in a directory.
     * Returns number of files minified.
     */
    public function minifyDirectory(string $directory): int
    {
        if (! File::isDirectory($directory)) {
            return 0;
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            $ext = strtolower($file->getExtension());

            if (! in_array($ext, ['html', 'htm', 'css', 'js'], true)) {
                continue;
            }

            $path = $file->getPathname();

            try {
                $original = File::get($path);
                $originalSize = strlen($original);

                $minified = match ($ext) {
                    'html', 'htm' => $this->minifyHtml($original),
                    'css'         => $this->minifyCss($original),
                    'js'          => $this->minifyJs($original),
                    default       => $original,
                };

                // Only write if we actually reduced size
                if (strlen($minified) < $originalSize) {
                    File::put($path, $minified);
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::warning("Minification failed for [{$path}]", ['error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    /**
     * Minify HTML — remove whitespace, comments, optional tags.
     */
    public function minifyHtml(string $html): string
    {
        // Preserve <pre>, <code>, <script>, <style>, <textarea> content
        $preserved = [];
        $index = 0;

        $preserveTags = ['pre', 'code', 'script', 'style', 'textarea'];

        foreach ($preserveTags as $tag) {
            $html = preg_replace_callback(
                "/<{$tag}\b[^>]*>.*?<\/{$tag}>/si",
                function ($match) use (&$preserved, &$index) {
                    $placeholder = "<!--PRESERVE_{$index}-->";
                    $preserved[$placeholder] = $match[0];
                    $index++;

                    return $placeholder;
                },
                $html
            );
        }

        // Remove HTML comments (but not conditional comments or cms markers)
        $html = preg_replace('/<!--(?!\[|cms:).*?-->/s', '', $html);

        // Remove whitespace between tags
        $html = preg_replace('/>\s+</', '> <', $html);

        // Collapse multiple spaces/newlines into single space
        $html = preg_replace('/\s{2,}/', ' ', $html);

        // Remove leading/trailing whitespace per line
        $html = preg_replace('/^\s+|\s+$/m', '', $html);

        // Restore preserved content
        foreach ($preserved as $placeholder => $content) {
            $html = str_replace($placeholder, $content, $html);
        }

        return trim($html);
    }

    /**
     * Minify CSS — remove whitespace, comments.
     */
    public function minifyCss(string $css): string
    {
        // Remove comments
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        // Remove whitespace around selectors and properties
        $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);

        // Collapse whitespace
        $css = preg_replace('/\s{2,}/', ' ', $css);

        // Remove trailing semicolons before closing brace
        $css = str_replace(';}', '}', $css);

        // Remove leading zeros (0.5 → .5)
        $css = preg_replace('/(:|\s)0\.(\d+)/', '$1.$2', $css);

        // Remove units from zero values (0px → 0)
        $css = preg_replace('/(:|\s)0(px|em|rem|%|vh|vw)/', '${1}0', $css);

        return trim($css);
    }

    /**
     * Minify JS — basic whitespace/comment removal.
     * Uses conservative approach to avoid breaking code.
     */
    public function minifyJs(string $js): string
    {
        // Remove single-line comments (but not URLs with //)
        $js = preg_replace('#(?<!:)//(?!/).*$#m', '', $js);

        // Remove multi-line comments
        $js = preg_replace('/\/\*.*?\*\//s', '', $js);

        // Collapse multiple newlines
        $js = preg_replace('/\n{2,}/', "\n", $js);

        // Remove leading/trailing whitespace per line
        $js = preg_replace('/^\s+|\s+$/m', '', $js);

        // Remove empty lines
        $js = preg_replace('/^\n/m', '', $js);

        return trim($js);
    }
}
