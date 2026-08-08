<?php

declare(strict_types=1);

namespace Kopling\Core\Extension\Contract;

use Kopling\Core\Extend\ModerationTarget;

/**
 * Declares a model as flaggable/moderatable -- what `kopling/moderation`'s report/hide/delete
 * flow can act on, entirely independent of whether `moderation` itself is installed or knows
 * this extension exists. Same shape as `ValidatesModels`: any extension can implement this for
 * any model, including one it doesn't own.
 */
interface RegistersModerationTargets
{
    /**
     * @return array<ModerationTarget>
     */
    public function moderationTargets(): array;
}
