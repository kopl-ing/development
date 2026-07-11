<?php

declare(strict_types=1);

namespace Kopling\Discussions;

use Kopling\Core\Authorization\Permission;
use Kopling\Core\Content\Moment;
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Discussions\Command\SeedDemoRepliesCommand;

class Extension extends AbstractExtension implements ChangesUx, HasCommands, HasPermissions, ExtendsModels
{
    public static function name(): string
    {
        return 'Discussions';
    }

    public static function description(): string
    {
        return 'A discussion page per moment, with an activity teaser and engage bar.';
    }

    /**
     * Two card additions, both reading the moment from `$context->subject`:
     * - `teaser` in the body after core's `content` -- the "N people used X words" line.
     * - `engage` in the footer -- Reply / Open discussion. `after` names the reactions
     *   extension's last footer entry so, when both are installed, reactions come first;
     *   it's a harmless no-op (dangling anchor) when reactions isn't present.
     */
    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-discussions::teaser')
            ->in('core::card.body')
            ->as('teaser')
            ->after('core::content')
            ->add('kopling-discussions::engage')
            ->in('core::card.footer')
            ->as('engage')
            ->after('kopling-reactions::words');
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SeedDemoRepliesCommand::class];
    }

    public function permissions(): array
    {
        return [
            new Permission(
                'view',
                __('kopling-discussions::permissions.view.label'),
                __('kopling-discussions::permissions.view.description'),
                default: true,
            ),
            new Permission(
                'reply',
                __('kopling-discussions::permissions.reply.label'),
                __('kopling-discussions::permissions.reply.description'),
                default: true,
            ),
        ];
    }

    public function models(): array
    {
        return [
            (new Model(Moment::class))
                ->relation((new Relation)->hasMany('replies', Reply::class)->eagerLoad()),
        ];
    }
}
