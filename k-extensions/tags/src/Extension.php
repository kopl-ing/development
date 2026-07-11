<?php

declare(strict_types=1);

namespace Kopling\Tags;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extend\Ux;
use Kopling\Tags\Command\SeedDemoTagsCommand;

class Extension extends AbstractExtension implements ChangesUx, HasCommands
{
    public static function name(): string
    {
        return 'Tags';
    }

    public static function description(): string
    {
        return 'Categorise moments with tags and browse everything under one.';
    }

    /**
     * A tag badge row at the top of each card's body (before core's own `content`), reading
     * the moment from `$context->subject`. Registered by anonymous-component tag, the same
     * way the reactions extension registers into the footer.
     */
    public function ux(): Ux
    {
        // `before` takes the anchor's fully-qualified id -- core's Content entry resolves to
        // `core::content` (see Card\Body::defaults), so the tag row sits above the title/body.
        return Ux::make()
            ->add('kopling-tags::tags')
            ->in('kopling-core::card.body')
            ->as('tags')
            ->before('kopling-core::content');
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SeedDemoTagsCommand::class];
    }
}
