<?php

declare(strict_types=1);

namespace Kopling\Example;

use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Extension\LoadOrder\LoadsAfter;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Storage\StorageAccess;
use Kopling\Core\Storage\StoragePermission;
use Kopling\Core\Storage\StorageRequest;
use Kopling\Core\Storage\StorageRetention;
use Kopling\Core\Ux\Portal\Navigation\Item;

/**
 * A dummy extension -- not meant to be installed for real functionality. It exists so every
 * path and convention an extension can use has one working, verified example: see the
 * sibling directories (views/, css/, js/, resources/css+js/, migrations/, routes/, lang/,
 * icon/) and CLAUDE.md ("Extension conventions") for what each one does.
 */
class Extension extends AbstractExtension implements ChangesUx, RequestsStorageDriver, HasPermissions, LoadsAfter, ExtendsPortals
{
    public static function name(): string
    {
        return 'Example';
    }

    public static function description(): string
    {
        return 'A dummy extension documenting every path and convention an extension can use.';
    }

    /**
     * Contracts are only needed for capabilities a directory convention can't express --
     * this one is illustrative only.
     *
     * @return array<StorageRequest>
     */
    public function storage(): array
    {
        return [
            new StorageRequest(
                id: 'avatars',
                label: 'Avatars',
                description: 'Profile pictures uploaded by members.',
                access: StorageAccess::Public,
                retention: StorageRetention::Persistent,
                permission: StoragePermission::ReadWrite,
            ),
        ];
    }

    /**
     * Written as just "manage-things" -- Manager prefixes it to "kopling-example::manage-things"
     * before it's registered, so this author never has to think about another extension's names.
     * label/description go through this extension's own lang/ (Section 4), same as any other
     * translatable string -- never a hardcoded string in the language the author happened to
     * write the extension in.
     *
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'manage-things',
                label: __('kopling-example::permissions.manage-things.label'),
                description: __('kopling-example::permissions.manage-things.description'),
            ),
        ];
    }

    /**
     * Reuses core's generic Item component rather than shipping a bespoke one -- the common
     * case. Passing the component's own class (resolved to its Blade tag by ComponentTag)
     * reads better than spelling out "k::portal.navigation.item" by hand, though either works.
     * `.when('manage-things')` reuses the permission declared above, prefixed to
     * "kopling-example::manage-things" by Manager the same way the entry's own id is.
     */
    public function ux(): Ux
    {
        return Ux::make()
            ->add(Item::class, ['label' => 'Hello', 'route' => 'kopling-core::community/example.hello'])
            ->in('kopling-core::community.navigation')
            ->as('hello')
            ->when('manage-things');
    }

    /**
     * Demonstrates every one of `PortalExtension`'s attachments at once, targeting Core's own
     * Community portal -- routes/, css/, js/, and resources/{css,js}/ (the compiled-asset
     * pipeline, see `compiledAssets()`'s own docblock) are the sibling directories this class's
     * own docblock already points at, now wired up rather than sitting unused. The second
     * attachment, to Admin's own Portal, has no routes/css/js of its own -- it exists purely to
     * demonstrate `compiledAssets()`'s auto-derived-per-Portal naming: this call resolves
     * `resources/{css,js}/kopling-admin-admin.{css,js}` (sanitized from `kopling-admin::admin`)
     * rather than colliding with the Community attachment's own `app.{css,js}` bundle above.
     *
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->routes(__DIR__.'/../routes/web.php')
                ->css(__DIR__.'/../css/app.css')
                ->js(__DIR__.'/../js/app.js')
                ->compiledAssets(__DIR__.'/..'),
            new PortalExtension('kopling-admin::admin')
                ->compiledAssets(__DIR__.'/..'),
        ];
    }

    /**
     * Illustrative only, like storage() above -- this extension doesn't actually register
     * anything into Admin's own Portal slots, but an extension that did (a settings screen, a
     * tools link) would need Admin's Portal already registered first, and this is the explicit,
     * self-declared way to say so: "I need to come after this specific package." A reference to
     * a package that isn't installed is silently ignored, never an error, the same
     * graceful-degradation rule ux()'s after()/before() apply to a dangling reference.
     *
     * See `Kopling\Core\Extension\LoadOrder\InfluencesLoadOrder` for the other half of the
     * mechanism -- letting a contract's own owner declare this same relationship for every
     * extension implementing it, without either side needing to know the other's package name.
     * `kopling/admin` itself now does exactly this for `HasAdminSettings` -- see its own
     * Extension.php.
     *
     * @return array<string>
     */
    public function loadAfter(): array
    {
        return ['kopling/admin'];
    }
}
