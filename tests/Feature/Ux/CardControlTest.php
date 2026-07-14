<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;
use Kopling\Core\Ux\Context;

/*
 * `Control` resolves `Control::SLOT` the same way `Top`/`Footer` do -- these swap the real,
 * container-bound `Manager` singleton for a `fakeManager()` instance built from a disposable
 * fixture (see tests/Pest.php), the same approach ContextGetSubjectUrlTest.php/ThemeTest.php
 * already use, so a real extension being installed or not can't affect the assertions.
 */

function swapControlEntries(array $extensions): void
{
    app()->instance(Manager::class, fakeManager($extensions));
}

it('renders no dropdown when Control::SLOT has no entries', function () {
    swapControlEntries([]);

    $html = (string) $this->blade('<x-k::card.control :context="$context" />', ['context' => new Context()]);

    expect($html)->not->toContain('popover');
});

it('renders a dropdown menu once an extension adds an entry to Control::SLOT', function () {
    swapControlEntries([
        'tests-fixtures/card-control-entry' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\CardControlEntry\\',
            'path' => __DIR__,
        ],
    ]);

    $html = (string) $this->blade('<x-k::card.control :context="$context" />', ['context' => new Context()]);

    expect($html)->toContain('popover')
        ->and(substr_count($html, '<li>'))->toBe(1);
});
