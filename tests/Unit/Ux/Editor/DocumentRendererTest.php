<?php

declare(strict_types=1);

use Kopling\Core\Ux\Editor\DocumentRenderer;
use Kopling\Core\Ux\Editor\EditorNode;

it('renders a plain paragraph as escaped HTML', function () {
    $json = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('<script>alert(1)</script>')]],
    ]);

    expect(DocumentRenderer::render($json, []))
        ->toBe('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>');
});

it('wraps an enabled mark but not a disabled one', function () {
    $json = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('hi', [['type' => 'bold']])]],
    ]);

    expect(DocumentRenderer::render($json, [EditorNode::Bold]))->toBe('<p><strong>hi</strong></p>')
        ->and(DocumentRenderer::render($json, []))->toBe('<p>hi</p>');
});

it('renders children of a disabled or unrecognized node without its own wrapping tag', function () {
    $json = editorDoc([
        ['type' => 'blockquote', 'content' => [
            ['type' => 'paragraph', 'content' => [editorText('quoted')]],
        ]],
    ]);

    expect(DocumentRenderer::render($json, []))->toBe('<p>quoted</p>')
        ->and(DocumentRenderer::render($json, [EditorNode::Blockquote]))->toBe('<blockquote><p>quoted</p></blockquote>');
});

it('renders paragraph/text/listItem/taskItem without needing them enabled', function () {
    $json = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('always allowed')]],
    ]);

    expect(DocumentRenderer::render($json, []))->toBe('<p>always allowed</p>');
});

it('clamps a heading level into 1-6', function () {
    $json = editorDoc([
        ['type' => 'heading', 'attrs' => ['level' => 99], 'content' => [editorText('big')]],
    ]);

    expect(DocumentRenderer::render($json, [EditorNode::Heading]))->toBe('<h6>big</h6>');
});

it('allows an http(s)/mailto link but strips a javascript: link', function () {
    $safe = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('click', [['type' => 'link', 'attrs' => ['href' => 'https://kopl.ing']]])]],
    ]);
    $unsafe = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('click', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]])]],
    ]);

    expect(DocumentRenderer::render($safe, [EditorNode::Link]))
        ->toBe('<p><a href="https://kopl.ing" rel="nofollow noopener noreferrer" target="_blank">click</a></p>')
        ->and(DocumentRenderer::render($unsafe, [EditorNode::Link]))->toBe('<p>click</p>');
});

it('returns an empty string for malformed JSON instead of throwing', function () {
    expect(DocumentRenderer::render('not json', []))->toBe('');
});

it('validate() throws on malformed JSON', function () {
    expect(fn () => DocumentRenderer::validate('not json', []))
        ->toThrow(InvalidArgumentException::class, 'not valid JSON');
});

it('validate() throws on an oversized document', function () {
    $json = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText(str_repeat('a', 200_000))]],
    ]);

    expect(fn () => DocumentRenderer::validate($json, []))
        ->toThrow(InvalidArgumentException::class, 'too large');
});

it('validate() throws when a node type outside the enabled set is used', function () {
    $json = editorDoc([
        ['type' => 'blockquote', 'content' => [
            ['type' => 'paragraph', 'content' => [editorText('quoted')]],
        ]],
    ]);

    expect(fn () => DocumentRenderer::validate($json, []))
        ->toThrow(InvalidArgumentException::class, 'disabled or unrecognized node type [blockquote]');
});

it('validate() throws when a mark type outside the enabled set is used', function () {
    $json = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('hi', [['type' => 'bold']])]],
    ]);

    expect(fn () => DocumentRenderer::validate($json, []))
        ->toThrow(InvalidArgumentException::class, 'disabled or unrecognized mark type [bold]');
});

it('validate() passes for a document using only always-allowed and enabled types', function () {
    $json = editorDoc([
        ['type' => 'paragraph', 'content' => [editorText('hi', [['type' => 'bold']])]],
    ]);

    DocumentRenderer::validate($json, [EditorNode::Bold]);
})->throwsNoExceptions();

it('validate() throws when the document is nested deeper than the ceiling', function () {
    $node = ['type' => 'paragraph', 'content' => [editorText('leaf')]];

    for ($i = 0; $i < 100; $i++) {
        $node = ['type' => 'blockquote', 'content' => [$node]];
    }

    $json = editorDoc([$node]);
    $enabled = [EditorNode::Blockquote];

    expect(fn () => DocumentRenderer::validate($json, $enabled))
        ->toThrow(InvalidArgumentException::class, 'nested too deeply');
});
