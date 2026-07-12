<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\BadThemeToken;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesTheme;

class Extension extends AbstractExtension implements ChangesTheme
{
    public static function name(): string
    {
        return 'Bad Theme Token Fixture';
    }

    public static function description(): string
    {
        return 'Declares a theme key that is not a real Token, for testing Manager::themes() validation.';
    }

    public function theme(): array
    {
        return [
            '--not-a-real-token' => '#ff0000',
        ];
    }
}
