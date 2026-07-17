<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Editor;

/**
 * Turns a ProseMirror/TipTap JSON document into trusted HTML, entirely server-side, at write
 * time -- deliberately NOT a sanitizer for arbitrary client-supplied HTML. It only knows how
 * to emit a closed, hand-mapped set of node/mark -> tag pairings (`EditorNode`'s cases, plus
 * the always-on base schema paragraph/text/listItem/taskItem/doc); there is no allowlist to
 * keep in sync with what the renderer can produce, because it structurally cannot emit a tag
 * it wasn't written to emit. All text is `htmlspecialchars`-escaped; a `link` mark's `href` is
 * scheme-allowlisted (http/https/mailto only) to block `javascript:` and similar.
 *
 * `render()` and `validate()` deliberately take different postures toward an unrecognized or
 * currently-disabled node/mark type: `validate()` (the input boundary, called from a
 * `FormRequest`) rejects it outright -- a document containing something outside the currently
 * enabled set is a 422, not silently accepted. `render()` (the output boundary, called at
 * display time from already-persisted rows) instead silently skips just that node/mark's own
 * wrapping tag while still rendering its children -- for forward/backward compatibility (an
 * `EditorNode` a row used at write time might since have been disabled) and as defense-in-depth
 * against anything that reached storage despite validation.
 */
class DocumentRenderer
{
    /**
     * A generous but bounded nesting ceiling -- catches a pathological/adversarial document
     * before it can blow the PHP call stack, not a realistic editor's own output.
     */
    protected const MAX_DEPTH = 64;

    /**
     * A rich-text post shouldn't need more than this; also bounds how much work `render()`/
     * `validate()` ever do on a single document.
     */
    protected const MAX_BYTES = 100_000;

    protected const ALWAYS_ALLOWED = ['doc', 'paragraph', 'listItem', 'taskItem'];

    protected const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * @param  array<EditorNode>  $enabled
     */
    public static function render(string $json, array $enabled): string
    {
        $doc = json_decode($json, true);

        if (! is_array($doc)) {
            return '';
        }

        return static::renderNode($doc, $enabled, 0);
    }

    /**
     * @param  array<EditorNode>  $enabled
     *
     * @throws \InvalidArgumentException if the document is oversized, malformed, too deeply
     *                                     nested, or uses a node/mark type outside `$enabled`
     */
    public static function validate(string $json, array $enabled): void
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Document is too large.');
        }

        $doc = json_decode($json, true);

        if (! is_array($doc)) {
            throw new \InvalidArgumentException('Document is not valid JSON.');
        }

        static::validateNode($doc, $enabled, 0);
    }

    /**
     * @param  array<EditorNode>  $enabled
     */
    protected static function renderNode(array $node, array $enabled, int $depth): string
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Document is nested too deeply to render.');
        }

        $type = $node['type'] ?? null;

        if ($type === 'text') {
            return static::renderText($node, $enabled);
        }

        $children = static::renderChildren($node, $enabled, $depth);

        if (! static::isAllowed($type, $enabled)) {
            return $children;
        }

        return match ($type) {
            'doc' => $children,
            'paragraph' => "<p>{$children}</p>",
            'heading' => static::renderHeading($node, $children),
            'bulletList' => "<ul>{$children}</ul>",
            'orderedList' => "<ol>{$children}</ol>",
            'listItem', 'taskItem' => "<li>{$children}</li>",
            'taskList' => '<ul data-type="taskList">'.$children.'</ul>',
            'blockquote' => "<blockquote>{$children}</blockquote>",
            'codeBlock' => "<pre><code>{$children}</code></pre>",
            'hardBreak' => '<br>',
            'horizontalRule' => '<hr>',
            default => $children,
        };
    }

    /**
     * @param  array<EditorNode>  $enabled
     */
    protected static function renderChildren(array $node, array $enabled, int $depth): string
    {
        $content = $node['content'] ?? [];

        if (! is_array($content)) {
            return '';
        }

        $rendered = '';

        foreach ($content as $child) {
            if (is_array($child)) {
                $rendered .= static::renderNode($child, $enabled, $depth + 1);
            }
        }

        return $rendered;
    }

    protected static function renderHeading(array $node, string $children): string
    {
        $level = max(1, min(6, (int) ($node['attrs']['level'] ?? 1)));

        return "<h{$level}>{$children}</h{$level}>";
    }

    /**
     * @param  array<EditorNode>  $enabled
     */
    protected static function renderText(array $node, array $enabled): string
    {
        $text = htmlspecialchars((string) ($node['text'] ?? ''), ENT_QUOTES, 'UTF-8');

        foreach ((array) ($node['marks'] ?? []) as $mark) {
            if (is_array($mark)) {
                $text = static::wrapMark($text, $mark, $enabled);
            }
        }

        return $text;
    }

    /**
     * @param  array<EditorNode>  $enabled
     */
    protected static function wrapMark(string $text, array $mark, array $enabled): string
    {
        $type = $mark['type'] ?? null;

        if (! static::isAllowed($type, $enabled)) {
            return $text;
        }

        return match ($type) {
            'bold' => "<strong>{$text}</strong>",
            'italic' => "<em>{$text}</em>",
            'strike' => "<s>{$text}</s>",
            'underline' => "<u>{$text}</u>",
            'code' => "<code>{$text}</code>",
            'link' => static::wrapLink($text, $mark),
            default => $text,
        };
    }

    protected static function wrapLink(string $text, array $mark): string
    {
        $href = (string) ($mark['attrs']['href'] ?? '');
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (! in_array($scheme, self::ALLOWED_LINK_SCHEMES, true)) {
            return $text;
        }

        $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

        return "<a href=\"{$safeHref}\" rel=\"nofollow noopener noreferrer\" target=\"_blank\">{$text}</a>";
    }

    /**
     * @param  array<EditorNode>  $enabled
     */
    protected static function validateNode(array $node, array $enabled, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('Document is nested too deeply.');
        }

        $type = $node['type'] ?? null;

        if ($type !== null && $type !== 'text' && ! static::isAllowed($type, $enabled)) {
            throw new \InvalidArgumentException("Document contains a disabled or unrecognized node type [{$type}].");
        }

        foreach ((array) ($node['marks'] ?? []) as $mark) {
            $markType = is_array($mark) ? ($mark['type'] ?? null) : null;

            if ($markType !== null && ! static::isAllowed($markType, $enabled)) {
                throw new \InvalidArgumentException("Document contains a disabled or unrecognized mark type [{$markType}].");
            }
        }

        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                static::validateNode($child, $enabled, $depth + 1);
            }
        }
    }

    /**
     * @param  array<EditorNode>  $enabled
     */
    protected static function isAllowed(?string $type, array $enabled): bool
    {
        if ($type === null) {
            return false;
        }

        if (in_array($type, self::ALWAYS_ALLOWED, true)) {
            return true;
        }

        $node = EditorNode::tryFrom($type);

        return $node !== null && in_array($node, $enabled, true);
    }
}
