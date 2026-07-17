<?php

declare(strict_types=1);

namespace Kopling\Discussions;

use Kopling\Core\Content\Moment;
use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Discussions\Command\SeedDemoRepliesCommand;

class Extension extends AbstractExtension implements ChangesUx, HasCommands, HasIcons, HasPermissions, ExtendsModels, ExtendsPortals
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
     *
     * `default-composer` is the discussion page's own reply form, in its own slot
     * (`kopling-discussions::show.composer`, resolved with the moment bound as `$context`) rather
     * than markup hardcoded into show.blade.php -- so an extension that wants to own the one
     * reply surface itself (reply-dock) can `Ux::remove('kopling-discussions::default-composer')`
     * outright instead of only CSS-hiding a form whose editor mounts regardless.
     */
    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-discussions::teaser')
            ->in('kopling-core::card.body')
            ->as('teaser')
            ->after('kopling-core::content')
            ->add('kopling-discussions::engage')
            ->in('kopling-core::card.footer')
            ->as('engage')
            ->after('kopling-reactions::words')
            ->add('kopling-discussions::composer')
            ->in('kopling-discussions::show.composer')
            ->as('default-composer');
    }

    /**
     * @return array<Icon>
     */
    public function icons(): array
    {
        return [
            new Icon(id: 'comment', label: 'Comment', default: 'fas-comment'),
        ];
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
                ->relation((new Relation)->hasMany('replies', Reply::class)->eagerLoad())
                ->linksTo('kopling-core::community/discussions.show'),
        ];
    }

    /**
     * The discussion page (`/m/{moment}`) renders inside Community's chrome, so its routes are
     * attached to the Community portal rather than left ungrouped -- this is what lets
     * `InjectPortal` resolve a real Portal for it now, instead of `Ux\Community\Chrome` having
     * to hardcode the lookup itself.
     *
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->routes(__DIR__.'/../routes/web.php'),
        ];
    }
}
