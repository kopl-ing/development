<?php

declare(strict_types=1);

namespace Kopling\Reactions;

use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extend\Ux;
use Kopling\Reactions\Command\SeedDemoReactionsCommand;

class Extension extends AbstractExtension implements ChangesUx, HasCommands
{
    public static function name(): string
    {
        return 'Reactions';
    }

    public static function description(): string
    {
        return 'Emoji and worded reactions for moments.';
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SeedDemoReactionsCommand::class];
    }

    /**
     * Fills the `core::card.footer` slot that `Card\Footer` deliberately leaves empty for a
     * real reactions feature, with two entries: the emoji `rail` (the calm aggregate) and the
     * `words` strip ("Latest reactions") after it. Both are registered by their anonymous
     * component tag, not a class -- extensions get an auto view namespace but not a
     * class-component namespace, so `ComponentTag` passes the tag through untouched and the
     * footer renders each via `<x-dynamic-component>`.
     */
    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-reactions::rail')
            ->in('kopling-core::card.footer')
            ->as('rail')
            ->add('kopling-reactions::words')
            ->in('kopling-core::card.footer')
            ->as('words')
            ->after('kopling-reactions::rail');
    }
}
