<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\AuthorizesModelsFixture;

use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsModels;

/**
 * A fixture extension registering `Extend\Model::authorize()` on a fake model class it doesn't
 * own (mirrors `ValidatesModelsFixture`'s own `Fixture\Target` -- the class never needs to
 * exist, since `Manager::authorizationRules()` reads the declared array, not a live model).
 * Two rules on the same "act" ability, `AggregatesModelsTest` combines with a second fixture
 * (`AuthorizesModelsFixtureTwo`) registered on the same fake class to prove aggregation collects
 * closures from every extension that declared one, not just the first.
 */
class Extension extends AbstractExtension implements ExtendsModels
{
    public static function name(): string
    {
        return 'Authorizes Models Fixture';
    }

    public static function description(): string
    {
        return 'Registers Extend\Model::authorize() on a fake model class, for testing Manager::authorizationRules().';
    }

    /**
     * @return array<ExtendModel>
     */
    public function models(): array
    {
        return [
            (new ExtendModel('Fixture\\AuthorizableTarget'))
                ->authorize('act', fn () => true),
        ];
    }
}
