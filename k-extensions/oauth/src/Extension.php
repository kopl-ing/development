<?php

declare(strict_types=1);

namespace Kopling\OAuth;

use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extend\Ux\ProvidesUxEntries;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Http\Controllers\AccountSettingsController;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Form\Field;
use Kopling\Core\Ux\Form\Input;
use Kopling\Core\Ux\Portal\Navigation\Item;
use Kopling\OAuth\Command\KeysCommand;

class Extension extends AbstractExtension implements ChangesUx, ExtendsPortals, HasAdminSettings, HasCommands, HasIcons, HasPermissions, HasPortals
{
    public static function name(): string
    {
        return 'OAuth';
    }

    public static function description(): string
    {
        return 'Lets apps and AI assistants act on a person\'s behalf, with their consent.';
    }

    /**
     * @return array<Icon>
     */
    public function icons(): array
    {
        return [
            new Icon(id: 'allowed', label: 'Allowed', default: 'fas-check'),
            new Icon(id: 'not-allowed', label: 'Not allowed', default: 'fas-xmark'),
            new Icon(id: 'app', label: 'Connected app', default: 'fas-plug'),
        ];
    }

    public function permissions(): array
    {
        return [
            new Permission(
                id: 'manage-oauth',
                label: __('kopling-oauth::permissions.manage-oauth.label'),
                description: __('kopling-oauth::permissions.manage-oauth.description'),
            ),
        ];
    }

    /**
     * `label` is a plain string: portals resolve before lang files are registered.
     *
     * @return array<Portal>
     */
    public function portals(): array
    {
        return [
            new Portal(
                id: 'oauth',
                label: 'Authorize apps',
                path: 'oauth',
                layout: 'kopling-oauth::layouts.oauth',
            ),
        ];
    }

    /**
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            (new PortalExtension('kopling-oauth::oauth'))->routes(__DIR__.'/../routes/oauth.php'),
            (new PortalExtension('kopling-core::community'))->routes(__DIR__.'/../routes/community.php'),
            (new PortalExtension('kopling-admin::admin'))->routes(__DIR__.'/../routes/admin.php'),
        ];
    }

    public function ux(): ProvidesUxEntries
    {
        return Ux::make()
            ->add('kopling-oauth::connected-apps')
            ->in(AccountSettingsController::SECTIONS_SLOT)
            ->as('connected-apps')
            ->add(Item::class, [
                'label' => __('kopling-oauth::messages.admin.nav'),
                'route' => 'kopling-admin::admin/oauth',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('admin-nav')
            ->when('kopling-oauth::manage-oauth');
    }

    /**
     * @return array<Field>
     */
    public function adminSettings(): array
    {
        return [
            new Field(
                id: 'access-token-minutes',
                label: 'Access token lifetime (minutes)',
                component: Input::class,
                default: '60',
                description: 'How long an app can act before it has to refresh its token. Shorter limits what a leaked token can do.',
                data: ['type' => 'number'],
            ),
            new Field(
                id: 'refresh-token-days',
                label: 'Refresh token lifetime (days)',
                component: Input::class,
                default: '30',
                description: 'How long an app stays connected without being used. After this the person has to approve it again.',
                data: ['type' => 'number'],
            ),
            new Field(
                id: 'personal-token-days',
                label: 'Personal token lifetime (days)',
                component: Input::class,
                default: '365',
                description: 'How long a token a person creates themselves (for tools without a sign-in screen) stays valid.',
                data: ['type' => 'number'],
            ),
        ];
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [KeysCommand::class];
    }
}
