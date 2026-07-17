<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Editor;

/**
 * Walks a ProseMirror/TipTap JSON document collecting its text nodes -- the plain-text
 * counterpart to `DocumentRenderer`, for anything that needs a word count or a short preview
 * rather than rendered HTML (e.g. `Reply::statsFor()`, a "+ Quote" button's own preview text).
 */
class PlainTextExtractor
{
    public static function extract(string $json): string
    {
        $doc = json_decode($json, true);

        if (! is_array($doc)) {
            return '';
        }

        return trim(preg_replace('/\s+/', ' ', static::collect($doc)) ?? '');
    }

    protected static function collect(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return (string) ($node['text'] ?? '');
        }

        $content = $node['content'] ?? [];

        if (! is_array($content)) {
            return '';
        }

        $parts = [];

        foreach ($content as $child) {
            if (is_array($child)) {
                $parts[] = static::collect($child);
            }
        }

        return implode(' ', $parts);
    }
}
