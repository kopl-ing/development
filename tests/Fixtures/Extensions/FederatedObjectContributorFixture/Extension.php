<?php

declare(strict_types=1);

namespace Tests\Fixtures\Extensions\FederatedObjectContributorFixture;

use Kopling\Core\Content\Moment;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsFederatedObjects;

/**
 * Contributes an `attachment` field to `Moment`'s outbound JSON-LD without owning `Moment` --
 * mirrors how a real image-gallery extension would federate its own images alongside a Moment
 * it doesn't own. Also attempts to override `type`, proving `Federation\Manager::toActivityJson()`
 * protects the envelope regardless of what a contribution returns.
 */
class Extension extends AbstractExtension implements ExtendsFederatedObjects
{
    public static function name(): string
    {
        return 'Federated Object Contributor Fixture';
    }

    public static function description(): string
    {
        return 'Adds an attachment field to Moment for testing ExtendsFederatedObjects.';
    }

    /**
     * @return array<class-string, \Closure>
     */
    public function federatedObjectContributions(): array
    {
        return [
            Moment::class => function (Moment $moment) {
                return [
                    'type' => 'Overridden',
                    'attachment' => [
                        ['type' => 'Image', 'url' => "https://example.test/fixtures/{$moment->id}.jpg"],
                    ],
                ];
            },
        ];
    }
}
