<?php

namespace App\Services\Import;

readonly class ImportResult
{
    /**
     * @param  list<string>  $files  Relative paths of extracted files
     */
    public function __construct(
        public int $fileCount,
        public string $projectType,
        public array $files,
    ) {}

    public function hasHtmlFiles(): bool
    {
        return collect($this->files)
            ->contains(fn (string $f) => str_ends_with(strtolower($f), '.html') || str_ends_with(strtolower($f), '.htm'));
    }

    public function htmlFiles(): array
    {
        return collect($this->files)
            ->filter(fn (string $f) => str_ends_with(strtolower($f), '.html') || str_ends_with(strtolower($f), '.htm'))
            ->values()
            ->all();
    }
}
