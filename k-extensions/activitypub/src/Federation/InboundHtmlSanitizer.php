<?php

declare(strict_types=1);

namespace Kopling\Activitypub\Federation;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Sanitizes a remote AP object's `content` (plain HTML -- Mastodon and the rest of the fediverse
 * never send ProseMirror JSON) directly into `body_html`, leaving `body` null -- there is no
 * canonical Tiptap document for federated content, so nothing should try to fabricate one (see
 * `moments`/`replies`' own `body` column: nullable by design, "a Moment can be entirely composed
 * of [something] with no body of its own"). Deliberately its own closed allowlist, not a reuse
 * of `Ux\Editor\DocumentRenderer` -- that one renders trusted ProseMirror JSON written by this
 * instance's own editor and structurally cannot accept arbitrary HTML at all; this is the
 * matching boundary for HTML instead, same philosophy (a closed, hand-mapped set of tags,
 * everything else dropped to its text content, links scheme-allowlisted).
 */
class InboundHtmlSanitizer
{
    protected const MAX_BYTES = 100_000;

    protected const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'code', 'pre',
        'ul', 'ol', 'li', 'blockquote', 'a',
    ];

    protected const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto'];

    public static function sanitize(string $html): string
    {
        if (strlen($html) > self::MAX_BYTES) {
            $html = substr($html, 0, self::MAX_BYTES);
        }

        $document = new DOMDocument();

        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8"?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $body = $document->getElementsByTagName('body')->item(0);

        return $body === null ? '' : static::renderChildren($body);
    }

    protected static function renderChildren(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= static::renderNode($child);
        }

        return $html;
    }

    protected static function renderNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->textContent, ENT_QUOTES, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);
        $children = static::renderChildren($node);

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            return $children;
        }

        if ($tag === 'br') {
            return '<br>';
        }

        if ($tag === 'a') {
            return static::renderLink($node, $children);
        }

        return "<{$tag}>{$children}</{$tag}>";
    }

    protected static function renderLink(DOMElement $node, string $children): string
    {
        $href = $node->getAttribute('href');
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (! in_array($scheme, self::ALLOWED_LINK_SCHEMES, true)) {
            return $children;
        }

        $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

        return "<a href=\"{$safeHref}\" rel=\"nofollow noopener noreferrer\" target=\"_blank\">{$children}</a>";
    }
}
