<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class BladeSecurityContractTest extends TestCase
{
    public function test_blank_target_links_use_noopener_and_noreferrer(): void
    {
        $violations = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file->getPathname());

            preg_match_all('/<[^>]+target=(["\'])_blank\1[^>]*>/i', (string) $contents, $matches);

            foreach ($matches[0] as $tag) {
                if (! preg_match('/\srel=(["\'])(?=[^"\']*\bnoopener\b)(?=[^"\']*\bnoreferrer\b)[^"\']*\1/i', $tag)) {
                    $violations[] = $file->getPathname().': '.$tag;
                }
            }
        }

        $this->assertSame([], $violations);
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function bladeFiles(): iterable
    {
        $root = resource_path('views');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                yield $file;
            }
        }
    }
}
