<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModelExtender;

use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsModels;

/**
 * A fixture extension exercising `ExtendsModels` end to end: a `hasMany` relation (proves
 * `Manager::models()`'s `resolveRelationUsing()` wiring actually resolves real rows), a cast
 * on two differently-based targets -- `Gadget` (extends `Database\Model`) and `Widget` (only
 * `use`s `HasExtendedCasts` directly, mimicking `Person`'s real constraint) -- proving both
 * paths read back the same registry `registerCasts()` populated, not two independent copies --
 * and a `perPage()` override on `Gadget` for `registerPerPage()`'s own equivalent test.
 */
class Extension extends AbstractExtension implements ExtendsModels
{
    public static function name(): string
    {
        return 'Model Extender Fixture';
    }

    public static function description(): string
    {
        return 'Adds a hasMany relation and casts to fixture models, for testing ExtendsModels.';
    }

    /**
     * @return array<ExtendModel>
     */
    public function models(): array
    {
        return [
            (new ExtendModel(Gadget::class))
                ->relation((new Relation)->hasMany('parts', Part::class))
                ->cast('metadata', 'array')
                ->perPage(5),
            (new ExtendModel(Widget::class))
                ->cast('notes', 'array'),
        ];
    }
}
