<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\ModerationTargetsFixture;

use Kopling\Core\Extend\ModerationTarget;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\RegistersModerationTargets;
use Kopling\Core\People\Group;

/**
 * Targets `Group` -- a real, always-present class with no `SoftDeletes` -- purely to exercise
 * `AggregatesModerationTargets`'s own alias-derivation/morph-map-registration/`softDeletable`
 * computation, not because `Group` is meant to be genuinely flaggable anywhere real.
 */
class Extension extends AbstractExtension implements RegistersModerationTargets
{
    public static function name(): string
    {
        return 'Moderation Targets Fixture';
    }

    public static function description(): string
    {
        return 'Registers a fixture moderation target, for testing RegistersModerationTargets.';
    }

    /**
     * @return array<ModerationTarget>
     */
    public function moderationTargets(): array
    {
        return [
            new ModerationTarget(
                model: Group::class,
                label: 'Group',
                preview: 'fixture::preview.group',
            ),
        ];
    }
}
