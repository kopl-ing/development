<?php

declare(strict_types=1);

namespace Kopling\Core\Extend;

/**
 * One model a `RegistersModerationTargets::moderationTargets()` declares as flaggable. `$alias`
 * and `$softDeletable` are set by `Manager`, never by the declaring extension -- deliberately
 * not constructor params, the same "derive it, don't let an author get it wrong" reasoning
 * `Manager::id()` already applies to every other local id in the codebase (permission ids,
 * Portal ids, `UxEntry` ids). See `Extension\Concerns\AggregatesModerationTargets`.
 */
class ModerationTarget
{
    /**
     * `Manager::id($declaringPackage).'-'.Str::kebab(class_basename($model))` -- also the
     * `Relation::morphMap()` key `Flag::flaggable_type` stores, registered by the same
     * aggregation pass that computes this.
     */
    public string $alias;

    /**
     * Whether `$model` uses `Illuminate\Database\Eloquent\SoftDeletes` -- a model that does
     * always supports both Hide (`delete()`) and Delete (`forceDelete()`); there's no scenario
     * where only one is available, so this is the single flag both `moderation`'s Hide and
     * Delete control-slot entries key off.
     */
    public bool $softDeletable;

    public function __construct(
        public readonly string $model,
        public readonly string $label,
        public readonly string $preview,
    ) {
    }
}
