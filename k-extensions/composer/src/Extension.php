<?php

declare(strict_types=1);

namespace Kopling\Composer;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Compose\Modes;
use Kopling\Core\Ux\Person\Avatar;

class Extension extends AbstractExtension implements ChangesUx, ExtendsPortals, HasIcons
{
    /**
     * The compose card's own Top/Body/Footer slot family -- same `?string $slot` reuse
     * Discussions' Reply cards established, never Core's own `Card\Top::SLOT`/etc., so a
     * Moment-only registration (reactions, poll, teaser) never bleeds into the compose form.
     * `CONTROL_SLOT` matters even though nothing registers into it: `Card\Top` renders `Control`
     * fixed (not a `TOP_SLOT` entry), defaulting to Core's own real `Card\Control::SLOT` when no
     * override is given -- without this, a real Pin/moderation action registered for Moments
     * would render on the compose form's still-unsaved `Moment::draft()`, whose `getSubject()`
     * has no real id for that action's own route to bind to.
     */
    public const TOP_SLOT = 'kopling-composer::compose.top';

    public const BODY_SLOT = 'kopling-composer::compose.body';

    public const FOOTER_SLOT = 'kopling-composer::compose.footer';

    public const CONTROL_SLOT = 'kopling-composer::compose.control';

    public static function name(): string
    {
        return 'Composer';
    }

    public static function description(): string
    {
        return 'Share a moment from the top of the feed.';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-composer::composer')
            ->in('kopling-core::community.content-top')
            ->as('composer')
            ->add('kopling-composer::title-field')
            ->in(self::TOP_SLOT)
            ->as('title')
            ->add(Modes::class)
            ->in(self::BODY_SLOT)
            ->as('modes')
            ->add('kopling-composer::footer-actions')
            ->in(self::FOOTER_SLOT)
            ->as('actions')
            ->add('kopling-composer::mode-text', [
                'icon' => 'kopling-composer::pen',
                'label' => __('kopling-composer::messages.mode_text'),
            ])
            ->in(Modes::SLOT)
            ->as('text')
            ->first();
    }

    /**
     * @return array<Icon>
     */
    public function icons(): array
    {
        return [
            new Icon(id: 'pen', label: 'Write', default: 'fas-pen'),
        ];
    }

    /**
     * The compose route + the [x-cloak] rule attach to Community, since the composer only ever
     * shows in that portal's feed. Routes ride the portal's own group (prefix + name +
     * "web" middleware); the css is linked onto Community pages via the head-assets outlet.
     *
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->routes(__DIR__.'/../routes/web.php')
                ->css(__DIR__.'/../css/app.css'),
        ];
    }
}
