<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ThemeDeclarer;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Ux\Theme\Token;

class Extension extends AbstractExtension implements ChangesTheme
{
    public static function name(): string
    {
        return 'Theme Declarer Fixture';
    }

    public static function description(): string
    {
        return 'Declares one valid theme token override, for testing ChangesTheme.';
    }

    public function theme(): array
    {
        return [
            Token::ColorAccent->value => '#ff0000',
        ];
    }
}
