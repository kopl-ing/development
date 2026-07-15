<?php

declare(strict_types=1);

namespace Kopling\ThemeMidnight;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Ux\Theme\ColorScheme;
use Kopling\Core\Ux\Theme\Token;

/**
 * A dark theme for Kopling, shipped as an ordinary extension -- the first real proof that a
 * theme is just a `ChangesTheme` implementor like any other capability, not a special-cased
 * "installed theme" concept. Only overrides the color tokens, deliberately: Midnight leaves
 * both radius tokens alone and lets them keep coming from the compiled "kopling" default --
 * proving the override is genuinely sparse, not an all-or-nothing full theme replacement.
 */
class Extension extends AbstractExtension implements ChangesTheme
{
    public static function name(): string
    {
        return 'Midnight';
    }

    public static function description(): string
    {
        return 'A dark theme for Kopling.';
    }

    /**
     * @return array<string, string>
     */
    public function theme(): array
    {
        return [
            Token::ColorBase100->value => '#0b1220',
            Token::ColorBase200->value => '#111827',
            Token::ColorBase300->value => '#1f2937',
            Token::ColorBaseContent->value => '#e5e7eb',
            Token::ColorPrimary->value => '#5b7fd6',
            Token::ColorPrimaryContent->value => '#0b1220',
            Token::ColorSecondary->value => '#334155',
            Token::ColorSecondaryContent->value => '#e5e7eb',
            Token::ColorAccent->value => '#ff7a33',
            Token::ColorAccentContent->value => '#0b1220',
            Token::ColorNeutral->value => '#0f172a',
            Token::ColorNeutralContent->value => '#cbd5e1',
        ];
    }

    public function colorScheme(): ColorScheme
    {
        return ColorScheme::Dark;
    }
}
