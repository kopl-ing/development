<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\AuthorizesModelsFixtureTwo;

use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsModels;

/**
 * A second, independent extension registering its own `authorize('act', ...)` rule on the exact
 * same fake model class `AuthorizesModelsFixture` uses -- proving two unrelated extensions can
 * each contribute a rule for the same model+ability without one overwriting the other, which a
 * single `Gate::policy()` class (1:1 per model) structurally couldn't do.
 */
class Extension extends AbstractExtension implements ExtendsModels
{
    public static function name(): string
    {
        return 'Authorizes Models Fixture Two';
    }

    public static function description(): string
    {
        return 'A second, independent authorize() registration on the same fake model class.';
    }

    /**
     * @return array<ExtendModel>
     */
    public function models(): array
    {
        return [
            (new ExtendModel('Fixture\\AuthorizableTarget'))
                ->authorize('act', fn () => false),
        ];
    }
}
