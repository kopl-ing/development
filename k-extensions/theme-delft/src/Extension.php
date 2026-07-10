<?php

declare(strict_types=1);

namespace Kopling\ThemeDelft;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Ux\Theme\Token;

/**
 * Delft -- Kopling's light, Delft-blue brand look (the kopl.ing identity: deep blue on a
 * near-white base, with the follow/sponsor orange as the single accent). Shipped as an
 * ordinary `ChangesTheme` extension, exactly like Midnight -- the same sparse token
 * override, just a light palette instead of a dark one. Overrides colours and both radius
 * tokens (the demo's rounded cards); everything else falls through to the compiled default.
 */
class Extension extends AbstractExtension implements ChangesTheme
{
    public static function name(): string
    {
        return 'Delft';
    }

    public static function description(): string
    {
        return 'The light Delft-blue brand theme for Kopling.';
    }

    /**
     * @return array<string, string>
     */
    public function theme(): array
    {
        return [
            Token::ColorBase100->value => '#fbfcfc',
            Token::ColorBase200->value => '#f1f4f8',
            Token::ColorBase300->value => '#dfe6f0',
            Token::ColorBaseContent->value => '#2a3346',
            Token::ColorPrimary->value => '#2b4a9b',
            Token::ColorPrimaryContent->value => '#ffffff',
            Token::ColorSecondary->value => '#16295e',
            Token::ColorSecondaryContent->value => '#ffffff',
            Token::ColorAccent->value => '#e8590c',
            Token::ColorAccentContent->value => '#ffffff',
            Token::ColorNeutral->value => '#1f2b46',
            Token::ColorNeutralContent->value => '#e6ebf5',
            Token::RadiusBox->value => '1rem',
            Token::RadiusField->value => '0.5rem',
        ];
    }
}
