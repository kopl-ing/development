<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\BadThemeValue;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Ux\Theme\Token;

class Extension extends AbstractExtension implements ChangesTheme
{
    public static function name(): string
    {
        return 'Bad Theme Value Fixture';
    }

    public static function description(): string
    {
        return 'Declares a real Token with a value that does not match its expected shape, for testing Manager::themes() validation.';
    }

    public function theme(): array
    {
        return [
            Token::ColorAccent->value => 'not-a-hex-color',
        ];
    }
}
