<?php

namespace App\Services\Parsers;

use App\Models\Site;

interface ParserInterface
{
    /**
     * Human-readable name of this parser strategy.
     */
    public function name(): string;

    /**
     * Discover all parseable page files in the repo.
     *
     * @return string[] Array of file paths relative to repo root
     */
    public function discoverPages(string $repoPath, Site $site): array;

    /**
     * Parse a single page file and extract metadata + regions.
     */
    public function parsePage(string $repoPath, string $filePath, Site $site): ?ParsedPage;
}
