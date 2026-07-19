<?php

declare(strict_types=1);

namespace Kopling\Tags;

use Kopling\Core\Content\Moment;
use Kopling\Core\Extend\Model;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extend\Relation;
use Kopling\Core\Extend\Ux;
use Kopling\Core\Extension\AbstractExtension;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\ValidatesModels;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Ux\Portal\Navigation\Item;
use Kopling\Tags\Command\SeedDemoTagsCommand;

class Extension extends AbstractExtension implements ChangesUx, ExtendsModels, ExtendsPortals, HasCommands, HasPermissions, ValidatesModels
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
     * @return array<Permission>
     */
    public function permissions(): array
    {
        return [
            new Permission(
                id: 'manage-tags',
                label: __('kopling-tags::permissions.manage-tags.label'),
                description: __('kopling-tags::permissions.manage-tags.description'),
            ),
        ];
    }

    /**
     * A tag badge row at the top of each card's body (before core's own `content`), reading
     * the moment from `$context->subject`. Registered by anonymous-component tag, the same
     * way the reactions extension registers into the footer. Also adds the admin nav entry
     * for the tag CRUD screen, gated behind `manage-tags`, matching how Admin's own Extension
     * gates its People/Groups/Settings nav entries.
     *
     * `select` fills composer's own `kopling-composer::compose.fields` slot with the tag
     * picker (`views/components/select.blade.php`) -- composer never declares anything about
     * tags itself. `min`/`max` both `null` for now (no constraint) -- if that ever changes,
     * update `modelValidationRules()` below's rule to match; the picker's own hint text and the
     * server-side enforcement have to agree.
     */
    public function ux(): Ux
    {
        // `before` takes the anchor's fully-qualified id -- core's Content entry resolves to
        // `core::content` (see Card\Body::defaults), so the tag row sits above the title/body.
        return Ux::make()
            ->add('kopling-tags::tags')
            ->in('kopling-core::card.body')
            ->as('tags')
            ->before('kopling-core::content')
            ->add(Item::class, [
                'label' => __('kopling-tags::messages.admin_tags'),
                'route' => 'kopling-admin::admin/tags',
            ])
            ->in('kopling-admin::admin.navigation')
            ->as('admin-nav')
            ->when('kopling-tags::manage-tags')
            ->add('kopling-tags::select', ['min' => null, 'max' => null])
            ->in('kopling-composer::compose.fields')
            ->as('select');
    }

    /**
     * The `tags` array the picker above posts -- validated here so `composer` never has to
     * declare anything about it, same split `TagsController`'s own merge for `Tag::class`
     * already established. No min/max enforced yet, matching the picker's own unconstrained
     * `ux()` registration above.
     *
     * @return array<class-string, array{rules: array<string, array<int, string>>, messages: array<string, string>}>
     */
    public function modelValidationRules(): array
    {
        return [
            Moment::class => [
                'rules' => [
                    'tags' => ['array'],
                    'tags.*' => ['exists:tags,id'],
                ],
                'messages' => [],
            ],
        ];
    }

    /**
     * @return array<class-string>
     */
    public function commands(): array
    {
        return [SeedDemoTagsCommand::class];
    }

    /**
     * Adds a `tags` relation to core's `Moment` (the pivot side, declared here so core's model
     * stays untouched), eager-loaded so a feed's tag rows read one batch-loaded relation per
     * moment instead of a `whereHas` per card -- the O(cards) cost issue #4 measured.
     * `Tag::forMoment` reads this relation.
     *
     * `saved()` is the actual write path for the tag picker `ux()` registers into composer's
     * form (see `views/components/select.blade.php`) -- fires post-insert (and post-update, for
     * whenever a real moment-edit flow exists), with the moment's own real id already assigned,
     * which `creating()`/`saving()` can't offer since a pivot sync needs the owning side's key.
     * Guarded on `request()->has('tags')`, not a default-to-empty read -- `saved()` fires on
     * *every* save of a `Moment`, including ones composer's own field was never part of (a
     * future title-only edit, a seeder), so defaulting a missing key to `[]` and syncing would
     * silently strip an unrelated save's tags. `composer` never learns tags exists either way.
     *
     * `$moment->load('tags')` after `sync()` is deliberate, not redundant -- `sync()` runs
     * through `$moment->tags()` (the *method*, a fresh relation query each call) rather than
     * the magic `$moment->tags` property accessor, so it never touches this same instance's own
     * cached relation. Composer renders its just-posted moment from this exact `$moment` object
     * (`kopling-composer::partials.moment`, the same request, no re-fetch from the DB) --
     * without this explicit reload, `Tag::forMoment()`'s own "load if not already loaded" check
     * would still be technically correct, but only a *second* request (a real page reload) would
     * ever see the freshly-synced tags; the one response that actually needs them wouldn't.
     *
     * @return array<Model>
     */
    public function models(): array
    {
        return [
            (new Model(Moment::class))
                ->relation((new Relation)->belongsToMany('tags', Tag::class, 'moment_tag')->eagerLoad())
                ->saved(function (Moment $moment) {
                    if (! request()->has('tags')) {
                        return;
                    }

                    $moment->tags()->sync(request()->input('tags', []));
                    $moment->load('tags');
                }),
        ];
    }

    /**
     * The public tag page (/tag/{slug}) attaches to Community — it reuses the base portal
     * shell + core's card component. Rides the portal's own group (web + prefix + name); route
     * name is now kopling-core::community/tags.show. The admin CRUD screen is a second,
     * separate attachment onto the Admin portal -- any extension can attach routes to any
     * portal it needs, same as `reactions`/`tags` already attach to Community.
     *
     * @return array<PortalExtension>
     */
    public function extendsPortals(): array
    {
        return [
            new PortalExtension('kopling-core::community')
                ->routes(__DIR__.'/../routes/web.php'),
            new PortalExtension('kopling-admin::admin')
                ->routes(__DIR__.'/../routes/admin.php'),
        ];
    }
}
