<?php

namespace App\Services\Parsers;

class ParsedPage
{
    public function __construct(
        public string $filePath,
        public ?string $urlPath = null,
        public ?string $title = null,
        public ?string $metaDescription = null,
        public ?string $metaKeywords = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public ?string $canonicalUrl = null,
        public ?array $schemaJson = null,
        public ?string $contentHash = null,
        public array $regions = [],
    ) {}
}
