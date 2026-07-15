<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ThemeA;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Ux\Theme\ColorScheme;
use Kopling\Core\Ux\Theme\Token;

/**
 * Paired with ThemeB for testing Kopling\Core\Ux\Theme's active-theme selection/rendering --
 * package name ("tests-fixtures/theme-a") is chosen so its id sorts alphabetically before
 * ThemeB's, making Theme::active()'s deterministic default predictable. Declares a different
 * colorScheme() than ThemeB so a test can prove the scheme follows the active theme too.
 */
class Extension extends AbstractExtension implements ChangesTheme
{
    public static function name(): string
    {
        return 'Theme A';
    }

    public static function description(): string
    {
        return 'Fixture theme, for testing Kopling\Core\Ux\Theme.';
    }

    public function theme(): array
    {
        return [
            Token::ColorPrimary->value => '#111111',
        ];
    }

    public function colorScheme(): ColorScheme
    {
        return ColorScheme::Light;
    }
}
