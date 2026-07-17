<?php

declare(strict_types=1);

namespace Kopling\Discussions\Support;

/**
 * Re-derives a ProseMirror document from a pre-editor-integration Reply's plain-text `body` --
 * pulled out of `2026_07_17_000001_backfill_reply_editor_documents`'s own anonymous migration
 * class (rather than left as a protected method there) specifically so it's a directly testable,
 * importable unit -- an anonymous migration class's own `require_once`/PHP-migrator caching makes
 * it effectively impossible to re-obtain the same instance from a test process that already ran
 * migrations once (a well-known Laravel anonymous-migration-class friction).
 *
 * Re-derives real `blockquote` nodes from the old "> Author: text" convention reply-dock used to
 * prepend as plain text (the same regex `discussions/views/partials/reply.blade.php` used to
 * parse at display time, before body_html made that unnecessary), so historical quoted replies
 * don't silently lose their quote styling once the migration backfills them.
 */
class LegacyReplyDocument
{
    /**
     * @return array<string, mixed>
     */
    public static function toDocument(string $body): array
    {
        $quotes = [];
        $lines = preg_split('/\r?\n/', $body) ?: [];
        $i = 0;

        while ($i < count($lines)) {
            $line = $lines[$i];

            if (preg_match('/^>\s?(.*)$/', $line, $m)) {
                [$author, $text] = array_pad(preg_split('/:\s+/', $m[1], 2) ?: [], 2, '');
                $quotes[] = $text === '' ? ['author' => '', 'text' => $author] : ['author' => $author, 'text' => $text];
                $i++;
            } elseif (trim($line) === '' && $quotes !== []) {
                $i++; // blank separator between quotes / before the reply text
            } else {
                break;
            }
        }

        $text = trim(implode("\n", array_slice($lines, $i)));

        $content = [];

        foreach ($quotes as $quote) {
            $quoteText = $quote['author'] !== '' ? "{$quote['author']}: {$quote['text']}" : $quote['text'];

            $content[] = [
                'type' => 'blockquote',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => $quoteText !== '' ? [['type' => 'text', 'text' => $quoteText]] : [],
                ]],
            ];
        }

        $content[] = [
            'type' => 'paragraph',
            'content' => $text !== '' ? [['type' => 'text', 'text' => $text]] : [],
        ];

        return ['type' => 'doc', 'content' => $content];
    }
}
