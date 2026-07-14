<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModelLinkerOverride;

use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Tests\Fixtures\Extensions\ModelExtender\Gadget;

/**
 * A second `linksTo()` declaration for the same fixture model `ModelLinker` targets, on a
 * different route -- proves `Context::getSubjectUrl()` resolves the last-registered declaration
 * when two extensions collide on the same model, the same rule `Manager::models()` already
 * applies to cast-key collisions.
 */
class Extension extends AbstractExtension implements ExtendsModels
{
    public static function name(): string
    {
        return 'Model Linker Override Fixture';
    }

    public static function description(): string
    {
        return 'Declares a second, colliding linksTo() route for the same fixture model, for testing last-registered-wins.';
    }

    /**
     * @return array<ExtendModel>
     */
    public function models(): array
    {
        return [
            (new ExtendModel(Gadget::class))
                ->linksTo('fixture-gadgets.other'),
        ];
    }
}
