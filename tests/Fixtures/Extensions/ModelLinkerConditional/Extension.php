<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModelLinkerConditional;

use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Tests\Fixtures\Extensions\ModelExtender\Gadget;

/**
 * A `linksTo()` declaration whose `$when` always evaluates false -- proves
 * `Context::getSubjectUrl()` suppresses the link rather than resolving `route()` regardless, the
 * same conditional-declaration shape `Relation::eagerLoad(callable)` already has a precedent for.
 */
class Extension extends AbstractExtension implements ExtendsModels
{
    public static function name(): string
    {
        return 'Model Linker Conditional Fixture';
    }

    public static function description(): string
    {
        return 'Declares a linksTo() route gated by a $when that always fails, for testing Context::getSubjectUrl().';
    }

    /**
     * @return array<ExtendModel>
     */
    public function models(): array
    {
        return [
            (new ExtendModel(Gadget::class))
                ->linksTo('fixture-gadgets.show', when: fn () => false),
        ];
    }
}
