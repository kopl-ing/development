<?php

declare(strict_types=1);

use Kopling\Core\Ux\Editor\EditorNode;

it('always includes Core\'s own default enabled set, even with no other extension installed', function () {
    $manager = fakeManager();

    expect($manager->editorNodes())
        ->toContain(EditorNode::Heading, EditorNode::Bold, EditorNode::Link)
        ->not->toContain(EditorNode::TaskList);
});

it('unions an extension\'s vote with Core\'s own defaults, deduped', function () {
    $manager = fakeManager([
        'tests-fixtures/editor-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\EditorDeclarer\\',
            'path' => __DIR__,
        ],
    ]);

    $nodes = $manager->editorNodes();

    expect($nodes)->toContain(EditorNode::TaskList, EditorNode::Heading)
        ->and(array_count_values(array_map(fn (EditorNode $node) => $node->value, $nodes)))
        ->each->toBe(1);
});
