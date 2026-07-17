<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;

/*
 * `Editor` resolves `Editor::SLOT` the same way `Card\Body`/`Top` do -- these swap the real,
 * container-bound `Manager` singleton for a `fakeManager()` instance built from a disposable
 * fixture (see tests/Pest.php), the same approach CardControlTest.php/ThemeTest.php already use.
 */

function swapEditorEntries(array $extensions): void
{
    app()->instance(Manager::class, fakeManager($extensions));
}

it('renders the notion editor mount by default, with no other extension installed', function () {
    swapEditorEntries([]);

    $html = (string) $this->blade('<x-k::editor name="body" />');

    expect($html)->toContain('data-tiptap-editor')
        ->and($html)->toContain('data-editor-name="body"');
});

it('renders no editor mount at all once an extension replaces the notion entry', function () {
    swapEditorEntries([
        'tests-fixtures/editor-replacer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\EditorReplacer\\',
            'path' => __DIR__,
        ],
    ]);

    $html = (string) $this->blade('<x-k::editor name="body" />');

    expect($html)->not->toContain('data-tiptap-editor');
});

it('passes name/value/placeholder through to the resolved editor component\'s own data', function () {
    swapEditorEntries([]);

    $html = (string) $this->blade('<x-k::editor name="reply" placeholder="Say something" />');

    expect($html)->toContain('data-editor-name="reply"')
        ->and($html)->toContain('data-editor-placeholder="Say something"');
});
