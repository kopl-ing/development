<?php

declare(strict_types=1);

use Kopling\Core\Ux\Editor\PlainTextExtractor;

it('collects text across nested nodes, joined with whitespace', function () {
    $json = editorDoc([
        ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [editorText('Title')]],
        ['type' => 'paragraph', 'content' => [editorText('Hello'), editorText(' world')]],
        ['type' => 'blockquote', 'content' => [
            ['type' => 'paragraph', 'content' => [editorText('quoted text')]],
        ]],
    ]);

    expect(PlainTextExtractor::extract($json))->toBe('Title Hello world quoted text');
});

it('returns an empty string for a document with no text nodes', function () {
    $json = editorDoc([
        ['type' => 'paragraph'],
    ]);

    expect(PlainTextExtractor::extract($json))->toBe('');
});

it('returns an empty string for malformed JSON instead of throwing', function () {
    expect(PlainTextExtractor::extract('not json'))->toBe('');
});

it('collapses internal whitespace and trims the result', function () {
    $json = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText("  a\n\nb  ")]],
    ]);

    expect(PlainTextExtractor::extract($json))->toBe('a b');
});
