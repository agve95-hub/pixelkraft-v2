<?php

namespace App\Services;

class NextMetadataPatcher
{
    public function canPatch(string $content): bool
    {
        return str_contains($content, 'export const metadata');
    }

    /**
     * @param array{
     *   title?: ?string,
     *   description?: ?string,
     *   keywords?: ?string,
     *   canonical?: ?string,
     *   og_title?: ?string,
     *   og_description?: ?string,
     *   og_image?: ?string
     * } $fields
     */
    public function patch(string $content, array $fields): string
    {
        $metadata = $this->extractMetadataObject($content);

        if (! $metadata) {
            throw new \RuntimeException('No `export const metadata` object was found in this Next.js file.');
        }

        $body = $metadata['body'];
        $body = $this->upsertObjectProperty($body, 'title', $this->formatString($fields['title'] ?? null), 1);
        $body = $this->upsertObjectProperty($body, 'description', $this->formatString($fields['description'] ?? null), 1);
        $body = $this->upsertObjectProperty($body, 'keywords', $this->formatString($fields['keywords'] ?? null), 1);
        $body = $this->upsertNestedObjectProperty($body, 'alternates', 'canonical', $this->formatString($fields['canonical'] ?? null), 1);
        $body = $this->upsertNestedObjectProperty($body, 'openGraph', 'title', $this->formatString($fields['og_title'] ?? null), 1);
        $body = $this->upsertNestedObjectProperty($body, 'openGraph', 'description', $this->formatString($fields['og_description'] ?? null), 1);
        $body = $this->upsertNestedObjectProperty($body, 'openGraph', 'images', $this->formatString($fields['og_image'] ?? null), 1);

        $replacement = "{\n".rtrim($body)."\n}";

        return substr_replace(
            $content,
            $replacement,
            $metadata['object_start'],
            $metadata['object_end'] - $metadata['object_start'] + 1
        );
    }

    /**
     * @return array{object_start: int, object_end: int, body: string}|null
     */
    private function extractMetadataObject(string $content): ?array
    {
        $metadataPos = strpos($content, 'export const metadata');

        if ($metadataPos === false) {
            return null;
        }

        $objectStart = strpos($content, '{', $metadataPos);

        if ($objectStart === false) {
            return null;
        }

        $objectEnd = $this->findMatchingBrace($content, $objectStart);

        if ($objectEnd === null) {
            return null;
        }

        return [
            'object_start' => $objectStart,
            'object_end' => $objectEnd,
            'body' => substr($content, $objectStart + 1, $objectEnd - $objectStart - 1),
        ];
    }

    private function upsertNestedObjectProperty(string $body, string $parentProperty, string $childProperty, ?string $formattedValue, int $indentLevel): string
    {
        $parentRange = $this->findTopLevelPropertyRange($body, $parentProperty);

        if (! $parentRange) {
            if ($formattedValue === null) {
                return $body;
            }

            $nestedBody = $this->upsertObjectProperty('', $childProperty, $formattedValue, $indentLevel + 1);
            $formattedObject = "{\n".rtrim($nestedBody)."\n".str_repeat('  ', $indentLevel).'}';

            return $this->upsertObjectProperty($body, $parentProperty, $formattedObject, $indentLevel);
        }

        $objectStart = strpos($body, '{', $parentRange['value_start']);

        if ($objectStart === false || $objectStart > $parentRange['value_end']) {
            if ($formattedValue === null) {
                return $body;
            }

            $nestedBody = $this->upsertObjectProperty('', $childProperty, $formattedValue, $indentLevel + 1);
            $formattedObject = "{\n".rtrim($nestedBody)."\n".str_repeat('  ', $indentLevel).'}';

            return $this->replacePropertyRange($body, $parentRange, $parentProperty, $formattedObject, $indentLevel);
        }

        $objectEnd = $this->findMatchingBrace($body, $objectStart);

        if ($objectEnd === null) {
            return $body;
        }

        $innerBody = substr($body, $objectStart + 1, $objectEnd - $objectStart - 1);
        $updatedInnerBody = $this->upsertObjectProperty($innerBody, $childProperty, $formattedValue, $indentLevel + 1);

        if (trim($updatedInnerBody) === '') {
            return $this->removePropertyRange($body, $parentRange);
        }

        $formattedObject = "{\n".rtrim($updatedInnerBody)."\n".str_repeat('  ', $indentLevel).'}';

        return $this->replacePropertyRange($body, $parentRange, $parentProperty, $formattedObject, $indentLevel);
    }

    private function upsertObjectProperty(string $body, string $property, ?string $formattedValue, int $indentLevel): string
    {
        $range = $this->findTopLevelPropertyRange($body, $property);

        if ($range) {
            if ($formattedValue === null) {
                return $this->removePropertyRange($body, $range);
            }

            return $this->replacePropertyRange($body, $range, $property, $formattedValue, $indentLevel);
        }

        if ($formattedValue === null) {
            return $body;
        }

        $line = str_repeat('  ', $indentLevel)."{$property}: {$formattedValue},";
        $trimmed = rtrim($body);

        if ($trimmed === '') {
            return $line."\n";
        }

        return $trimmed."\n".$line."\n";
    }

    /**
     * @return array{start: int, end: int, value_start: int, value_end: int}|null
     */
    private function findTopLevelPropertyRange(string $body, string $property): ?array
    {
        $length = strlen($body);
        $depth = 0;
        $stringDelimiter = null;
        $escapeNext = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($stringDelimiter !== null) {
                if ($escapeNext) {
                    $escapeNext = false;

                    continue;
                }

                if ($char === '\\') {
                    $escapeNext = true;

                    continue;
                }

                if ($char === $stringDelimiter) {
                    $stringDelimiter = null;
                }

                continue;
            }

            if (in_array($char, ['"', "'", '`'], true)) {
                $stringDelimiter = $char;

                continue;
            }

            if ($char === '{' || $char === '[' || $char === '(') {
                $depth++;

                continue;
            }

            if ($char === '}' || $char === ']' || $char === ')') {
                $depth--;

                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            if (substr($body, $i, strlen($property)) !== $property) {
                continue;
            }

            $previous = $i > 0 ? $body[$i - 1] : "\n";
            if (preg_match('/[\w$]/', $previous)) {
                continue;
            }

            $cursor = $i + strlen($property);
            while ($cursor < $length && ctype_space($body[$cursor])) {
                $cursor++;
            }

            if (($body[$cursor] ?? null) !== ':') {
                continue;
            }

            $valueStart = $cursor + 1;
            while ($valueStart < $length && ctype_space($body[$valueStart])) {
                $valueStart++;
            }

            $valueEnd = $this->findValueEnd($body, $valueStart);

            if ($valueEnd === null) {
                return null;
            }

            $propertyStart = $i;
            while ($propertyStart > 0 && $body[$propertyStart - 1] !== "\n") {
                $propertyStart--;
            }

            $propertyEnd = $valueEnd;
            while ($propertyEnd < $length && ctype_space($body[$propertyEnd])) {
                $propertyEnd++;
            }

            if (($body[$propertyEnd] ?? null) === ',') {
                $propertyEnd++;
            }

            while ($propertyEnd < $length && $body[$propertyEnd] === "\r") {
                $propertyEnd++;
            }

            if (($body[$propertyEnd] ?? null) === "\n") {
                $propertyEnd++;
            }

            return [
                'start' => $propertyStart,
                'end' => $propertyEnd,
                'value_start' => $valueStart,
                'value_end' => $valueEnd,
            ];
        }

        return null;
    }

    private function findValueEnd(string $body, int $start): ?int
    {
        $length = strlen($body);
        $depth = 0;
        $stringDelimiter = null;
        $escapeNext = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $body[$i];

            if ($stringDelimiter !== null) {
                if ($escapeNext) {
                    $escapeNext = false;

                    continue;
                }

                if ($char === '\\') {
                    $escapeNext = true;

                    continue;
                }

                if ($char === $stringDelimiter) {
                    $stringDelimiter = null;
                }

                continue;
            }

            if (in_array($char, ['"', "'", '`'], true)) {
                $stringDelimiter = $char;

                continue;
            }

            if ($char === '{' || $char === '[' || $char === '(') {
                $depth++;

                continue;
            }

            if ($char === '}' || $char === ']' || $char === ')') {
                if ($depth === 0) {
                    return $i;
                }

                $depth--;

                continue;
            }

            if ($char === ',' && $depth === 0) {
                return $i;
            }
        }

        return $length;
    }

    /**
     * @param  array{start: int, end: int}  $range
     */
    private function removePropertyRange(string $body, array $range): string
    {
        return substr_replace($body, '', $range['start'], $range['end'] - $range['start']);
    }

    /**
     * @param  array{start: int, end: int}  $range
     */
    private function replacePropertyRange(string $body, array $range, string $property, string $formattedValue, int $indentLevel): string
    {
        $line = str_repeat('  ', $indentLevel)."{$property}: {$formattedValue},\n";

        return substr_replace($body, $line, $range['start'], $range['end'] - $range['start']);
    }

    private function findMatchingBrace(string $content, int $openBracePos): ?int
    {
        $length = strlen($content);
        $depth = 0;
        $stringDelimiter = null;
        $escapeNext = false;

        for ($i = $openBracePos; $i < $length; $i++) {
            $char = $content[$i];

            if ($stringDelimiter !== null) {
                if ($escapeNext) {
                    $escapeNext = false;

                    continue;
                }

                if ($char === '\\') {
                    $escapeNext = true;

                    continue;
                }

                if ($char === $stringDelimiter) {
                    $stringDelimiter = null;
                }

                continue;
            }

            if (in_array($char, ['"', "'", '`'], true)) {
                $stringDelimiter = $char;

                continue;
            }

            if ($char === '{') {
                $depth++;

                continue;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function formatString(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $escaped = addcslashes($trimmed, "\\'");

        return "'{$escaped}'";
    }
}
