<?php

declare(strict_types=1);

use Kopling\Core\Ux\Editor\DocumentRenderer;
use Kopling\Core\Ux\Editor\EditorNode;
use Kopling\Discussions\Support\LegacyReplyDocument;

it('wraps plain legacy text into a single-paragraph document', function () {
    expect(LegacyReplyDocument::toDocument('Hello world'))->toBe([
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => 'Hello world']],
        ]],
    ]);
});

it('re-derives a blockquote from the old "> Author: text" convention', function () {
    $document = LegacyReplyDocument::toDocument("> Alice: nice post\n\nThanks!");

    expect($document)->toBe([
        'type' => 'doc',
        'content' => [
            [
                'type' => 'blockquote',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Alice: nice post']],
                ]],
            ],
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Thanks!']],
            ],
        ],
    ]);
});

it('produces a document that DocumentRenderer can render back into styled HTML', function () {
    $document = LegacyReplyDocument::toDocument("> Alice: nice post\n\nThanks!");

    $html = DocumentRenderer::render(json_encode($document), EditorNode::cases());

    expect($html)->toBe('<blockquote><p>Alice: nice post</p></blockquote><p>Thanks!</p>');
});
