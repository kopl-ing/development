<?php

declare(strict_types=1);

namespace Kopling\ReplyDock;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Portal\PortalExtension;

/**
 * The reply dock from kopling.convoro.co: on a discussion page, a sticky bar at the bottom that
 * morphs into a composer when you reply. Registers into the chrome footer's composer slot
 * (empty on the feed — the feed's composer is content-top), renders only on a discussion page
 * for a signed-in person, and posts through the discussions extension's own reply route. It
 * supersedes discussions' built-in inline reply form (hidden via css/app.css, linked onto
 * Community pages by extendsPortals()) so there's one reply surface, not two. Inline Alpine
 * morph + htmx — no bundled JS.
 */
class Extension extends AbstractExtension implements ChangesUx, ExtendsPortals, HasIcons
{
    public static function name(): string
    {
        return 'Reply dock';
    }

    public static function description(): string
    {
        return 'A sticky reply dock on discussion pages — a bar that morphs into a composer.';
    }

    public function ux(): Ux
    {
        return Ux::make()
            ->add('kopling-reply-dock::dock')
            ->in('kopling-core::community.composer')
            ->as('reply-dock');
    }

    /**
     * @return array<Icon>
     */
    public function icons(): array
    {
        return [
            new Icon(id: 'follow', label: 'Follow', default: 'fas-user-plus'),
            new Icon(id: 'report', label: 'Report', default: 'fas-flag'),
            new Icon(id: 'reply', label: 'Reply', default: 'fas-reply'),
        ];
    }

    /**
     * css/app.css hides discussions' built-in inline reply form + styles the scrubber dock,
     * linked onto Community pages via the head-assets outlet. No js of its own — the dock's
     * scrubber and the multi-quote flow are inline x-data + window events (an ext can't register
     * an Alpine store before core's Alpine.start()). No routes either — it posts through
     * discussions' reply route.
     *
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->css(__DIR__.'/../css/app.css'),
        ];
    }
}
