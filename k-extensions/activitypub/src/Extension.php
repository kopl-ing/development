<?php

declare(strict_types=1);

namespace Kopling\Activitypub;

use Kopling\Core\Content\Moment;
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\Contract\ListensToEvents;
use Kopling\Core\People\Person;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Form\Field;
use Kopling\Core\Ux\Form\TextArea;

class Extension extends AbstractExtension implements ExtendsModels, ExtendsPortals, HasAdminSettings, HasCommands, HasPermissions, HasPortals, ListensToEvents
{
    public static function name(): string
    {
        return 'ActivityPub';
    }

    public static function description(): string
    {
        return 'ActivityPub federation -- actor/object discovery, HTTP Signatures, inbox/outbox, and the delivery queue.';
    }

    /**
     * No layout -- every route in this Portal returns JSON-LD, never a Blade view.
     * `middleware: ['api']` (stateless, no session/CSRF) is what actor/webfinger/inbox routes
     * need, overriding every other Portal's hardcoded `['web']`.
     *
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'activitypub',
                label: 'ActivityPub',
                path: '',
                layout: null,
                middleware: ['api'],
            ),
        ];
    }

    /**
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'manage-federation',
                label: __('kopling-activitypub::permissions.manage-federation.label'),
                description: __('kopling-activitypub::permissions.manage-federation.description'),
            ),
        ];
    }

    /**
     * @return array<Field>
     */
    public function adminSettings(): array
    {
        return [
            new Field(
                id: 'blocked-domains',
                label: __('kopling-activitypub::settings.blocked_domains.label'),
                component: TextArea::class,
                description: __('kopling-activitypub::settings.blocked_domains.description'),
            ),
        ];
    }

    /**
     * @return array<Model>
     */
    public function models(): array
    {
        return [
            new Model(Person::class)
                ->relation((new Relation)->hasOne('activitypubActor', ActivitypubActor::class)),
            new Model(Moment::class)
                ->relation((new Relation)->morphOne('activitypubObject', ActivitypubObject::class, 'federatable')),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            (new PortalExtension('kopling-activitypub::activitypub'))
                ->routes(__DIR__.'/../routes/ap.php'),
        ];
    }

    /**
     * Laravel's own native model-lifecycle event, not a Kopling domain event -- there's no
     * `MomentCreated` (core dispatches `QueryingMoments` but nothing on create). Deliberately
     * not an `Extend\Model(Moment::class)->saved(...)` hook instead: that fires on every save
     * (create and update alike, guarded on `wasRecentlyCreated`), whereas this event only ever
     * fires once, on create.
     *
     * @return array<string, class-string>
     */
    public function listen(): array
    {
        return [
            'eloquent.created: '.Moment::class => Listeners\DeliverNewMomentListener::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [Command\DeliverPendingCommand::class];
    }
}
