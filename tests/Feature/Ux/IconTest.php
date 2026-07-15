<?php

declare(strict_types=1);

use Kopling\Core\Extension\Manager;
use Kopling\Core\Settings\Settings;

/*
 * `Icon` touches the real `svg()` helper (Blade Icons' own Factory, a real container binding)
 * and reads `Settings` (a real DB-backed table), so -- same reasoning `CardControlTest`/
 * `ThemeTest` already document -- these swap the real, container-bound `Manager` singleton for
 * a `fakeManager()` instance built from disposable fixtures, rather than a bare unit test.
 */

function swapIcons(array $extensions): void
{
    app()->instance(Manager::class, fakeManager($extensions));
}

it('renders a declared icon\'s Font Awesome default when no icon pack is active', function () {
    swapIcons([
        'tests-fixtures/icon-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\IconDeclarer\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/IconDeclarer',
        ],
    ]);

    $html = (string) $this->blade('<x-k::icon name="tests-fixtures-icon-declarer::widget" />');

    expect(trim($html))->toBe(svg('fas-cube', '', ['width' => '1em', 'height' => '1em'])->toHtml());
});

it('renders the active icon pack\'s own icon when it maps the requested id', function () {
    swapIcons([
        'tests-fixtures/icon-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\IconDeclarer\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/IconDeclarer',
        ],
        'tests-fixtures/icon-pack-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\IconPackDeclarer\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/IconPackDeclarer',
        ],
    ]);

    Settings::set('kopling-core::icon-pack', 'tests-fixtures-icon-pack-declarer');

    $html = (string) $this->blade('<x-k::icon name="tests-fixtures-icon-declarer::widget" />');

    expect(trim($html))->toBe(svg('fas-square', '', ['width' => '1em', 'height' => '1em'])->toHtml());
});

it('defaults to a 1em x 1em size when no width/height is passed, but an explicit one still wins', function () {
    swapIcons([
        'tests-fixtures/icon-declarer' => [
            'namespace' => 'Tests\\Fixtures\\Extensions\\IconDeclarer\\',
            'path' => __DIR__.'/../../Fixtures/Extensions/IconDeclarer',
        ],
    ]);

    $default = (string) $this->blade('<x-k::icon name="tests-fixtures-icon-declarer::widget" />');
    $explicit = (string) $this->blade('<x-k::icon name="tests-fixtures-icon-declarer::widget" width="24" height="24" />');

    expect(trim($default))->toContain('width="1em" height="1em"')
        ->and(trim($explicit))->toContain('width="24" height="24"')
        ->and(trim($explicit))->not->toContain('1em');
});

it('throws for a name nothing ever declared via HasIcons', function () {
    swapIcons([]);

    expect(fn () => $this->blade('<x-k::icon name="does-not-exist::icon" />'))
        ->toThrow('Unknown icon');
});
