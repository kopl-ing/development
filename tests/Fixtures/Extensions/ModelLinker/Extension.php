<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModelLinker;

use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Tests\Fixtures\Extensions\ModelExtender\Gadget;

/**
 * A fixture extension exercising `ExtendsModels`' `linksTo()` end to end -- targets the same
 * `Gadget` fixture model `ModelExtender` uses, since a `linksTo()` declaration needs a real,
 * persistable Eloquent model to resolve a subject/key against, and there's no reason to invent a
 * second one.
 */
class Extension extends AbstractExtension implements ExtendsModels
{
    public static function name(): string
    {
        return 'Model Linker Fixture';
    }

    public static function description(): string
    {
        return 'Declares a linksTo() route for a fixture model, for testing Context::getSubjectUrl().';
    }

    /**
     * @return array<ExtendModel>
     */
    public function models(): array
    {
        return [
            (new ExtendModel(Gadget::class))
                ->linksTo('fixture-gadgets.show'),
        ];
    }
}
