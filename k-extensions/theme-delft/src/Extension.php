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
        // Values track the charter's stated Delft palette (kopl.ing/charter.html §10): a
        // near-white base, a light-blue surface tint, slate text, deep-blue primary/secondary,
        // and the reserved follow/sponsor orange as the single accent.
        return [
            Token::ColorBase100->value => '#fbfcfc',       // page
            Token::ColorBase200->value => '#f2f4f3',       // raised surface (cards)
            Token::ColorBase300->value => '#d9e2f1',       // light-blue borders / hover
            Token::ColorBaseContent->value => '#3a4358',   // slate text
            Token::ColorPrimary->value => '#2b4a9b',       // Delft blue
            Token::ColorPrimaryContent->value => '#ffffff',
            Token::ColorSecondary->value => '#16295e',     // deep navy
            Token::ColorSecondaryContent->value => '#ffffff',
            Token::ColorAccent->value => '#e8590c',        // follow / sponsor ONLY
            Token::ColorAccentContent->value => '#ffffff',
            Token::ColorNeutral->value => '#1f2b46',
            Token::ColorNeutralContent->value => '#e6ebf5',
            Token::RadiusBox->value => '1rem',
            Token::RadiusField->value => '0.5rem',
        ];
    }
}
