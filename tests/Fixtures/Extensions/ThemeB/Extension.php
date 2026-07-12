<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ThemeB;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Ux\Theme\Token;

/**
 * Paired with ThemeA for testing Kopling\Core\Ux\Theme's active-theme selection/rendering.
 * Deliberately overrides the same token ThemeA does (ColorPrimary), with a different value, so
 * a test can prove only the active theme's value ever appears -- never both merged together.
 */
class Extension extends AbstractExtension implements ChangesTheme
{
    public static function name(): string
    {
        return 'Theme B';
    }

    public static function description(): string
    {
        return 'Fixture theme, for testing Kopling\Core\Ux\Theme.';
    }

    public function theme(): array
    {
        return [
            Token::ColorPrimary->value => '#222222',
        ];
    }
}
