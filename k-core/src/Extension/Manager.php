<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Collection;
use Kopling\Core\Core;
use Kopling\Core\Database\Model as DatabaseModel;
use Kopling\Core\Extend\Icon;
use Kopling\Core\Extend\Model as ExtendModel;
use Kopling\Core\Extend\Permission;
use Kopling\Core\Extension\Contract\CannotBeDisabled;
use Kopling\Core\Extension\Contract\ChangesEditor;
use Kopling\Core\Extension\Contract\ChangesIcons;
use Kopling\Core\Extension\Contract\ChangesTheme;
use Kopling\Core\Extension\Contract\ChangesUx;
use Kopling\Core\Extension\Contract\ExtendsModels;
use Kopling\Core\Extension\Contract\ExtendsPortals;
use Kopling\Core\Extension\Contract\HasAdminSettings;
use Kopling\Core\Extension\Contract\HasCommands;
use Kopling\Core\Extension\Contract\HasIcons;
use Kopling\Core\Extension\Contract\HasPermissions;
use Kopling\Core\Extension\Contract\HasPortals;
use Kopling\Core\Extension\Contract\ListensToEvents;
use Kopling\Core\Extension\Contract\ValidatesModels;
use Kopling\Core\Extension\Contract\RequestsStorageDriver;
use Kopling\Core\Extension\LoadOrder\Resolver;
use Kopling\Core\Portal\Portal;
use Kopling\Core\Portal\PortalExtension;
use Kopling\Core\Settings\EnabledExtensions;
use Kopling\Core\Storage\StorageRequest;
use Kopling\Core\Ux\Editor\EditorNode;
use Kopling\Core\Ux\Form\Field;
use Kopling\Core\Ux\Theme\ColorScheme;
use Kopling\Core\Ux\Theme\Token;
use Kopling\Core\Ux\UxAction;
use Kopling\Core\Ux\UxEntry;

class Manager
{
    protected ?Collection $models = null;

    public function __construct(
        protected Manifest $manifest,
        protected Dispatcher $events,
        protected RegistrationCache $cache,
    )
    {
    }

    /**
     * `Core` (keyed `'kopling/core'`, its real Composer package name) is always the first
     * entry, guaranteed present -- it isn't Composer-discovered the way the rest are (it
     * declares no `"type": "kopling-extension"` package of its own), it's the one thing
     * `Manager` always loads regardless. Every other entry is a genuinely discovered
     * extension, keyed by Composer package name, instantiated once, then ordered by
     * `LoadOrder\Resolver` -- Composer's own `installed.json` order carries no meaning beyond
     * being the alphabetical tie-break base `Resolver::resolve()` sorts from.
     *
     * `$includeDisabled` picks which of two results this returns: `false` (the default, and
     * what every other aggregator in this class -- `permissions()`, `ux()`, `portals()`,
     * `models()`, `listeners()`, `adminSettings()`, `commands()` -- and `ServiceProvider::boot()`
     * call with no argument) filters out anything `EnabledExtensions::isEnabled()` says is
     * disabled, except `CannotBeDisabled` implementors, which are exempt. `true` is the raw,
     * unfiltered view -- for the admin extensions-list page and `ListExtensionRegistrations`,
     * which both need to show disabled extensions too, not just active ones. Filtering after
     * `Resolver::resolve()` is safe: `Resolver::edges()` already treats a missing package as
     * "not installed" and degrades gracefully, same rule dangling `Ux::after()`/
     * `PortalExtension` references already follow.
     *
     * Memoized via `once()` (`spatie/once`) rather than a hand-rolled nullable property --
     * `once()` folds the enclosing call's own argument values into its cache key (not just
     * file+line), so `extensions(false)` and `extensions(true)` memoize independently from one
     * `once()` call site, scoped to this `Manager` instance the same way `models()`'s own
     * "cached on the instance, `Manager` is a singleton" reasoning already works.
     *
     * @return array<string, AbstractExtension>
     */
    public function extensions(bool $includeDisabled = false): array
    {
        return \once(function () use ($includeDisabled) {
            $discovered = ['kopling/core' => new Core()];

            foreach ($this->manifest->extensions() as $package => $extension) {
                $class = $extension['namespace'].'Extension';

                if (! class_exists($class) || ! is_subclass_of($class, AbstractExtension::class)) {
                    continue;
                }

                $discovered[$package] = new $class();
            }

            $resolved = Resolver::resolve($discovered);

            if ($includeDisabled) {
                return $resolved;
            }

            return array_filter(
                $resolved,
                fn (AbstractExtension $extension, string $package) => $extension instanceof CannotBeDisabled
                    || EnabledExtensions::isEnabled($this->id($package)),
                ARRAY_FILTER_USE_BOTH,
            );
        });
    }

    /**
     * Directory-convention paths this package declares, keyed by kind. An extension gets these
     * registered purely by the directory existing -- no contract to implement, no code to write.
     *
     * Deliberately doesn't include routes/css/js: those always need a target Portal to attach
     * to (which prefix/middleware, which page's `<head>`), so a bare "the directory exists" rule
     * can't express them the way it can migrations/views/lang -- see `ExtendsPortals`/
     * `PortalExtension` instead.
     *
     * @return array<string, string>
     */
    public function conventions(string $package): array
    {
        $path = $this->path($package);

        if ($path === null) {
            return [];
        }

        $conventions = [];

        foreach (['migrations', 'views', 'lang'] as $kind) {
            $find = realpath($path.'/'.$kind);

            if ($find && is_dir($path.'/'.$kind)) {
                $conventions[$kind] = $find;
            }
        }

        return $conventions;
    }

    public function path(string $package): ?string
    {
        if ($package === 'kopling/core') {
            return __DIR__ . '/../../';
        }

        return $this->manifest->extensions()[$package]['path'] ?? null;
    }

    /**
     * The namespace an extension's views/translations/etc. get registered under. Includes
     * the vendor, not just the package name -- two different vendors can both publish an
     * extension called "example", and dropping the vendor would make their view/translation
     * namespaces collide (kopling/example and acme/example would both register "example::").
     */
    public function id(string $package): string
    {
        return str_replace('/', '-', $package);
    }

    /**
     * Resolves a user-typed extension reference -- Composer package name ("kopling/example"),
     * its derived id ("kopling-example"), or short name ("example") -- back to the Composer
     * package name every other `Manager` method keys by. Null when nothing installed matches.
     * Shared by every `kopling:extensions:*` command so each accepts the same three reference
     * forms consistently, rather than each command re-implementing its own matching rules.
     */
    public function resolvePackage(string $needle): ?string
    {
        foreach (array_keys($this->extensions(includeDisabled: true)) as $package) {
            if ($needle === $package
                || $needle === $this->id($package)
                || $needle === basename(str_replace('\\', '/', $package))
            ) {
                return $package;
            }
        }

        return null;
    }

    /**
     * Storage requests declared by every extension, grouped by extension id the same way
     * `id()` namespaces views/translations -- so the admin storage-mapping screen can show
     * which extension owns each request instead of one anonymous, flattened list. Within
     * that, `StorageRequest::$id` is itself also prefixed the same way `permissions()`/
     * `portals()`/`ux()` prefix theirs, so two extensions declaring the same local id (e.g.
     * both wanting an "avatars" purpose) don't collide.
     *
     * @return array<string, array<StorageRequest>>
     */
    public function storageDrivers(): array
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['storageDrivers'])
                ->map(fn (array $requests) => array_map(fn (array $data) => StorageRequest::fromArray($data), $requests))
                ->all();
        }

        $requests = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof RequestsStorageDriver) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->storage())->ensure(StorageRequest::class);

            foreach ($declared as $request) {
                $request->id = $prefix.$request->id;
            }

            $requests[$this->id($package)] = $declared->all();
        }

        return $requests;
    }

    /**
     * Every admin-editable setting declared by every extension, grouped by owning extension id
     * -- same reasoning `storageDrivers()` groups by owner, so Admin's settings page can section
     * fields by which extension owns them. `Field::$id` is prefixed the same way
     * `permissions()`/`portals()` prefix theirs, so it doubles as a collision-safe persistence
     * key (see `Settings`).
     *
     * @return Collection<string, array<Field>>
     */
    public function adminSettings(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['adminSettings'])
                ->map(fn (array $fields) => array_map(fn (array $data) => Field::fromArray($data), $fields));
        }

        $settings = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasAdminSettings) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->adminSettings())->ensure(Field::class);

            foreach ($declared as $field) {
                $field->id = $prefix.$field->id;
            }

            $settings[$this->id($package)] = $declared->all();
        }

        return collect($settings);
    }

    /**
     * Every artisan command class declared by every extension. Unlike `permissions()`/
     * `portals()`/`ux()`, nothing here gets prefixed -- a command is referenced by its own
     * fully-qualified class name, already namespaced to the extension that declared it, so
     * there's no local id to collide with another extension's.
     *
     * `HasCommands::commands()` returns class-strings, not instantiated objects, so there's
     * no `Collection::ensure()` type to check against -- each entry is instead verified with
     * `is_subclass_of(..., Command::class)`, the equivalent guard against a `HasCommands`
     * implementor returning something that isn't actually an artisan command class.
     *
     * @return array<class-string>
     */
    public function commands(): array
    {
        if (($cached = $this->cache->get()) !== null) {
            return $cached['commands'];
        }

        $commands = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof HasCommands) {
                continue;
            }

            foreach ($extension->commands() as $command) {
                if (! is_string($command) || ! is_subclass_of($command, Command::class)) {
                    throw new \UnexpectedValueException(
                        sprintf('HasCommands::commands() should only include Command class-strings, but %s found.', get_debug_type($command))
                    );
                }

                $commands[] = $command;
            }
        }

        return $commands;
    }

    /**
     * Every extra validation rule (and custom message) any extension contributes for a model it
     * doesn't own, keyed by the target model's fully-qualified class name -- e.g. `reactions`
     * contributing `upvote_emoji`/`downvote_emoji` rules for `Kopling\Tags\Tag`. Two extensions
     * contributing a rule for the exact same field on the exact same model is last-declared-wins,
     * same collision convention `models()`'s cast registry already uses -- an edge case unlikely
     * enough not to warrant its own resolution rule.
     *
     * @return array<class-string, array{rules: array<string, mixed>, messages: array<string, string>}>
     */
    public function modelValidationRules(): array
    {
        if (($cached = $this->cache->get()) !== null) {
            return $cached['modelValidations'];
        }

        $declared = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ValidatesModels) {
                continue;
            }

            foreach ($extension->modelValidationRules() as $class => $definition) {
                $existing = $declared[$class] ?? ['rules' => [], 'messages' => []];

                $declared[$class] = [
                    'rules' => array_merge($existing['rules'], $definition['rules'] ?? []),
                    'messages' => array_merge($existing['messages'], $definition['messages'] ?? []),
                ];
            }
        }

        return $declared;
    }

    /**
     * The step every `ValidatesModels` consumer otherwise re-derives by hand: merge a
     * controller's own base rules/messages for `$modelClass` with whatever
     * `modelValidationRules()` aggregated for it. Returns the merged pair rather than calling
     * `$request->validate()` itself -- a `FormRequest`'s own `rules()`/`messages()` can't call
     * `validate()` on itself (that's the mechanism validating *them*), so the caller decides how
     * to use the result: hand both straight to `$request->validate($rules, $messages)` in a
     * plain controller, or return `['rules']`/`['messages']` from a `FormRequest`'s own methods.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array{rules: array<string, mixed>, messages: array<string, string>}
     */
    public function mergeModelValidationRules(string $modelClass, array $rules, array $messages = []): array
    {
        $extra = $this->modelValidationRules()[$modelClass] ?? ['rules' => [], 'messages' => []];

        return [
            'rules' => array_merge($rules, $extra['rules']),
            'messages' => array_merge($messages, $extra['messages']),
        ];
    }

    /**
     * Every permission declared by every extension, with `Permission::$id` already prefixed
     * with the owning extension's `id()` -- an author writes just the local part
     * ("manage-reactions"), never the prefix, so it can't drift or collide with another
     * extension's names.
     *
     * @return array<Permission>
     */
    public function permissions(): array
    {
        if (($cached = $this->cache->get()) !== null) {
            return array_map(fn (array $data) => Permission::fromArray($data), $cached['permissions']);
        }

        $permissions = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasPermissions) {
                continue;
            }

            $declared = collect($extension->permissions())->ensure(Permission::class);

            foreach ($declared as $permission) {
                $permission->id = $this->id($package).'::'.$permission->id;

                $permissions[] = $permission;
            }
        }

        return $permissions;
    }

    /**
     * Every Portal declared by every extension, with `Portal::$id` -- and `$permission`, when
     * set -- prefixed with the owning extension's `id()`, same authoring rule and collision-
     * safety reasoning as `permissions()`/`ux()`'s `$condition`. A future first-party
     * Moderation-portal extension (charter D29's own named proof case) slots into this same
     * loop for free.
     *
     * @return Collection<int, Portal>
     */
    public function portals(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['portals'])->map(fn (array $data) => Portal::fromArray($data))->keyBy('id');
        }

        $portals = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasPortals) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->portals())->ensure(Portal::class);

            foreach ($declared as $portal) {
                $portal->id = $prefix.$portal->id;

                if ($portal->permission !== null) {
                    $portal->permission = $prefix.$portal->permission;
                }

                $portals[] = $portal;
            }
        }

        return collect($portals)->keyBy('id');
    }

    /**
     * Every `PortalExtension` declared by every extension, grouped by the target Portal's
     * fully-qualified id -- the routes/css/js attachment counterpart to `portals()`'s identity
     * declarations. `PortalExtension::$portal` is a foreign reference (same convention as
     * `ux()`'s `after`/`before`), never prefixed here: targeting a Portal id that isn't actually
     * registered (a typo, or the declaring extension isn't installed) is a silent no-op, the
     * same graceful-degradation rule a dangling `ux()` anchor gets -- nothing to attach to, so
     * nothing happens, never an error.
     *
     * @return Collection<string, Collection<int, PortalExtension>>
     */
    public function portalExtensions(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['portalExtensions'])->map(
                fn (array $group) => collect($group)->map(fn (array $data) => PortalExtension::fromArray($data))
            );
        }

        $extensions = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ExtendsPortals) {
                continue;
            }

            $declared = collect($extension->extendsPortals())->ensure(PortalExtension::class);

            foreach ($declared as $portalExtension) {
                $extensions[$portalExtension->portal][] = $portalExtension;
            }
        }

        return collect($extensions)->map(fn (array $group) => collect($group));
    }

    /**
     * Flat, key-addressable registry of every css/js file any `PortalExtension` declared, plus
     * every installed extension's own `icon/lg.png`/`icon/sm.png` (see `extend.html`'s icon
     * convention) if present -- keyed by a stable hash of its own already-validated absolute
     * path rather than anything derived from a request -- `Http\Controllers\
     * ExtensionAssetController` looks a request's `key` up against this map and serves exactly
     * the matched path, so a request can never walk this into an arbitrary filesystem read the
     * way a raw `{package}/{path}`-shaped route parameter would invite. Icons are collected from
     * `extensions(includeDisabled: true)` -- unlike css/js, a disabled extension's icon still
     * needs to render (greyed out) on the admin extensions-list page.
     *
     * @return Collection<string, array{path: string, mime: string}>
     */
    public function extensionAssets(): Collection
    {
        $assets = [];

        foreach ($this->portalExtensions() as $group) {
            foreach ($group as $portalExtension) {
                foreach (['css' => 'text/css', 'js' => 'application/javascript'] as $kind => $mime) {
                    $path = $portalExtension->{$kind};

                    if ($path === null) {
                        continue;
                    }

                    $assets[static::assetKey($path)] = ['path' => $path, 'mime' => $mime];
                }
            }
        }

        foreach (array_keys($this->extensions(includeDisabled: true)) as $package) {
            $root = $this->path($package);

            if ($root === null) {
                continue;
            }

            foreach (['lg', 'sm'] as $size) {
                $path = $root.'/icon/'.$size.'.png';

                if (! is_file($path)) {
                    continue;
                }

                $assets[static::assetKey($path)] = ['path' => $path, 'mime' => 'image/png'];
            }
        }

        return collect($assets);
    }

    /**
     * The URL a `<link>`/`<script>` tag should point at for a `PortalExtension`'s css/js file,
     * or null when it didn't declare one -- shared by `views/layouts/partials/head.blade.php`
     * and `extensionAssets()` so both always agree on the same key.
     */
    public static function assetUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return route('kopling-core::assets', ['key' => static::assetKey($path)]);
    }

    /**
     * The URL an extension's `icon/{$size}.png` should be rendered from, or null if it didn't
     * ship one at that size -- only `lg` is required per `extend.html`'s icon convention, `sm`
     * is optional. Mirrors `assetUrl()`, sharing the same key/serving mechanism.
     */
    public function iconUrl(string $package, string $size = 'lg'): ?string
    {
        $root = $this->path($package);

        if ($root === null) {
            return null;
        }

        $path = $root.'/icon/'.$size.'.png';

        return is_file($path) ? static::assetUrl($path) : null;
    }

    protected static function assetKey(string $path): string
    {
        return hash('xxh3', $path);
    }

    /**
     * Registers every model extension declared by every extension -- relations directly against
     * the target model via `Model::resolveRelationUsing()`, `creating()`/`saving()`/`saved()`
     * hooks via the target model's own native Eloquent equivalents (no base-class requirement,
     * unlike casts below), casts into `Database\Model`'s own flat, class-keyed cast registry
     * (`Database\Model::registerCasts()`, read by its `getCasts()` override) -- a side effect,
     * not an aggregation like `permissions()`/`portals()`, so there's nothing meaningful to
     * return beyond what `ListExtensionRegistrations`-style introspection might want (mirrors
     * `listeners()` otherwise). An `Extend\Model` instance is scoped to exactly one model class
     * by its own constructor, so there's exactly one place "which model" is declared; where more
     * than one extension targets the same model, their `relations`/`casts` are combined, not
     * replaced -- a relation-name collision is last-registered-wins (`resolveRelationUsing()`'s
     * own rule), a cast-key collision is last-declared-wins among extensions, though core's own
     * `$casts` always wins regardless of declaration order (see `Database\Model::getCasts()`).
     * `creating`/`saving`/`saved` hooks never collide -- Eloquent supports multiple listeners per
     * event natively, so every extension's hook for the same model/event fires, in load order.
     *
     * `Collection::ensure(Extend\Model::class)` guards `ExtendsModels::models()` itself --
     * every item it returns must actually be a `Kopling\Core\Extend\Model` extender, not some
     * other value an implementor mistakenly returned.
     *
     * Cached on the instance (`Manager` is bound as a singleton) so the `resolveRelationUsing()`/
     * `creating()`/`saving()`/`saved()`/cast-registry side effects only ever run once, the same
     * reasoning `extensions()` already caches on.
     */
    public function models(): Collection
    {
        if ($this->models !== null) {
            return $this->models;
        }

        $declared = collect();

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ExtendsModels) {
                continue;
            }

            $declared->push(...$extension->models());
        }

        $declared->ensure(ExtendModel::class);

        $casts = [];

        $declared->each(function (ExtendModel $model) use (&$casts) {
            if (! class_exists($model->model)) {
                return;
            }

            /** @var class-string<EloquentModel> $class */
            $class = $model->model;

            foreach ($model->relations as $definition) {
                $class::resolveRelationUsing(
                    $definition['name'],
                    function (EloquentModel $instance) use ($definition) {
                        return $instance->{$definition['method']}(...$definition['constraint']);
                    }
                );
            }

            if ($model->creating !== null) {
                $class::creating($model->creating);
            }

            if ($model->saving !== null) {
                $class::saving($model->saving);
            }

            if ($model->saved !== null) {
                $class::saved($model->saved);
            }

            $casts[$model->model] = array_merge($casts[$model->model] ?? [], $model->casts);
        });

        DatabaseModel::registerCasts($casts);

        $this->models = $declared;

        return $declared;
    }

    /**
     * Every theme declared by every extension, keyed by owning extension id -- so an eventual
     * theme-picker UI can show which extension a theme came from, the same reasoning
     * `storageDrivers()` is keyed by owner instead of flattened. Unlike every other collector
     * here, keys inside each theme's array are never prefixed -- they're `Token::*->value`
     * strings naming a specific CSS custom property, a fixed catalog Manager itself checks
     * against, not a name space collision concern between extensions. An unrecognized key, or a
     * value that doesn't match that token's expected shape (`Token::matches()`), throws
     * immediately: a `ChangesTheme` implementor's own bug, not a foreign reference that might
     * legitimately not exist yet (contrast with `ux()`'s `after`/`before`).
     *
     * Each theme's tokens are kept separate here, not merged -- picking one active theme among
     * several installed ones is `Theme::active()`/`Theme::resolve()`'s job, not this method's.
     *
     * @return Collection<string, array<string, string>>
     */
    public function themes(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['themes']);
        }

        $themes = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ChangesTheme) {
                continue;
            }

            $declared = $extension->theme();

            foreach ($declared as $token => $value) {
                $case = Token::tryFrom($token);

                if ($case === null) {
                    throw new \InvalidArgumentException(
                        "[{$package}] declared an unrecognized theme token [{$token}]."
                    );
                }

                if (! $case->matches($value)) {
                    throw new \InvalidArgumentException(
                        "[{$package}]'s theme token [{$token}] has an invalid value [{$value}]."
                    );
                }
            }

            $themes[$this->id($package)] = $declared;
        }

        return collect($themes);
    }

    /**
     * Every installed theme's `colorScheme()`, keyed by the same id `themes()` uses -- kept as
     * its own collector for the same reason `themeChoices()` is: no reason to pay for token
     * validation just to read the one enum value `Theme::css()` needs to decide native form-
     * control/scrollbar chrome. No validation branch needed here the way `themes()` has one for
     * token values -- `ColorScheme` being a backed enum makes an invalid value unrepresentable.
     *
     * @return Collection<string, ColorScheme>
     */
    public function themeColorSchemes(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['themeColorSchemes'])->map(
                fn (string $scheme) => ColorScheme::from($scheme)
            );
        }

        $schemes = [];

        foreach ($this->extensions() as $package => $extension) {
            if ($extension instanceof ChangesTheme) {
                $schemes[$this->id($package)] = $extension->colorScheme();
            }
        }

        return collect($schemes);
    }

    /**
     * Every installed theme as `[id => label]` for the theme switcher -- id the same key
     * `themes()` uses (so a picked id selects that theme's token set), label the extension's
     * own `name()`. Kept separate from `themes()` so the switcher can list themes without
     * paying to validate every token, and so a theme with no currently-valid tokens still
     * shows up as a choice.
     *
     * @return array<string, string>
     */
    public function themeChoices(): array
    {
        $choices = [];

        foreach ($this->extensions() as $package => $extension) {
            if ($extension instanceof ChangesTheme) {
                $choices[$this->id($package)] = $extension::name();
            }
        }

        return $choices;
    }

    /**
     * Every `EditorNode` any installed extension (Core included) has voted to enable, unioned
     * (idempotent -- enabling something twice is the same as once) and keyed by its own
     * `->value` to dedupe. Unlike `permissions()`/`icons()`, nothing here is prefixed: these
     * aren't independently-namespaced declarations, they're votes into one shared, closed
     * catalog, same non-prefixing reasoning `themes()` already applies to `Token` keys.
     * `Collection::ensure(EditorNode::class)` guards `ChangesEditor::editor()` itself, same
     * role `ensure()` plays for every other collector here.
     *
     * @return array<EditorNode>
     */
    public function editorNodes(): array
    {
        if (($cached = $this->cache->get()) !== null) {
            return array_map(fn (string $value) => EditorNode::from($value), $cached['editorNodes']);
        }

        $nodes = [];

        foreach ($this->extensions() as $extension) {
            if (! $extension instanceof ChangesEditor) {
                continue;
            }

            $declared = collect($extension->editor())->ensure(EditorNode::class);

            foreach ($declared as $node) {
                $nodes[$node->value] = $node;
            }
        }

        return array_values($nodes);
    }

    /**
     * Every icon declared by every extension (Core included), with `Icon::$id` already
     * prefixed with the owning extension's `id()` -- an author writes just the local part
     * ("home"), never the prefix, same collision-safety rule as `permissions()`. Keyed by that
     * same fully-qualified id (like `portals()`), so `Ux\Icon` can resolve a reference straight
     * to its declared `Icon` (and its Font Awesome `$default`) with a single `get()`.
     *
     * @return Collection<string, Icon>
     */
    public function icons(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['icons'])->map(fn (array $data) => Icon::fromArray($data))->keyBy('id');
        }

        $icons = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof HasIcons) {
                continue;
            }

            $prefix = $this->id($package).'::';
            $declared = collect($extension->icons())->ensure(Icon::class);

            foreach ($declared as $icon) {
                $icon->id = $prefix.$icon->id;

                $icons[$icon->id] = $icon;
            }
        }

        return collect($icons)->keyBy('id');
    }

    /**
     * Every installed icon pack as `[id => label]`, the same shape `themeChoices()` gives the
     * theme switcher -- id the same key `iconPackMappings()` uses, label the extension's own
     * `name()`.
     *
     * @return array<string, string>
     */
    public function iconPackChoices(): array
    {
        $choices = [];

        foreach ($this->extensions() as $package => $extension) {
            if ($extension instanceof ChangesIcons) {
                $choices[$this->id($package)] = $extension::name();
            }
        }

        return $choices;
    }

    /**
     * Every icon pack's own `Icon::$id => its own icon name` map, keyed by the owning
     * extension's id -- the `ChangesIcons` counterpart to `icons()`'s declarations. Unlike
     * `themes()`, never validated against a fixed catalog here: a mapped id that isn't (or
     * isn't yet) a real declared `Icon` is left for `Ux\Icon` to silently fall back past at
     * render time, the same tolerant handling `ux()`'s `after`/`before` already give a dangling
     * reference -- an icon pack should be free to map every id it knows about regardless of
     * which of them are actually installed on a given site.
     *
     * @return Collection<string, array<string, string>>
     */
    public function iconPackMappings(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['iconPackMappings']);
        }

        $mappings = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ChangesIcons) {
                continue;
            }

            $mappings[$this->id($package)] = $extension->iconMap();
        }

        return collect($mappings);
    }

    /**
     * Every UxEntry declared by every extension (Core included, same as permissions()),
     * resolved down to what's actually registered once every extension's `Add`/`Replace`/
     * `Remove` operations have run, in `extensions()` order. `Add` entries get `UxEntry::$id`
     * -- and `$condition`, when it's a permission-id string, not a closure -- prefixed the
     * same way `permissions()` prefixes `Permission::$id`; `$slot`/`$after`/`$before` are left
     * untouched, since they're fully-qualified references the author writes out in full, not
     * private names Manager owns the prefixing of. `Replace`/`Remove` target another entry's
     * already fully-qualified id (same convention as `after`/`before`), so they're applied as
     * given, never prefixed. A `Replace`/`Remove` targeting an entry that isn't registered
     * (never was, or belongs to an extension processed later, or not installed at all) is a
     * no-op, same graceful-degradation rule `SlotResolver` applies to a dangling `after`/
     * `before` -- which also means an extension can only replace/remove something an
     * earlier-processed extension (or Core) already registered, not one processed after it.
     *
     * @return Collection<int, UxEntry>
     */
    public function ux(): Collection
    {
        if (($cached = $this->cache->get()) !== null) {
            return collect($cached['ux'])->map(fn (array $data) => UxEntry::fromArray($data));
        }

        $registry = [];

        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ChangesUx) {
                continue;
            }

            $prefix = $this->id($package).'::';

            foreach ($extension->ux()->entries() as $entry) {
                match ($entry->action) {
                    UxAction::Add => $this->applyUxAdd($registry, $entry, $prefix),
                    UxAction::Replace => $this->applyUxReplace($registry, $entry),
                    UxAction::Remove => $this->applyUxRemove($registry, $entry),
                };
            }
        }

        return collect(array_values($registry));
    }

    /**
     * Registers every listener declared by every extension directly against the event
     * dispatcher -- a side effect, not an aggregation like `ux()`/`permissions()`, so there's
     * nothing to return.
     */
    public function listeners(): void
    {
        foreach ($this->extensions() as $package => $extension) {
            if (! $extension instanceof ListensToEvents) {
                continue;
            }

            foreach ($extension->listen() as $event => $listener) {
                match (true) {
                    is_string($event) => $this->events->listen($event, $listener),
                    default => $this->events->subscribe($listener)
                };
            }
        }
    }

    /**
     * @param  array<string, UxEntry>  $registry
     */
    protected function applyUxAdd(array &$registry, UxEntry $entry, string $prefix): void
    {
        $entry->id = $prefix.$entry->id;

        // A condition already containing "::" is a foreign reference to another extension's
        // (or Core's) already-qualified permission id -- same convention $after/$before/
        // PortalExtension::$portal already use -- left exactly as written, never prefixed.
        if (is_string($entry->condition) && ! str_contains($entry->condition, '::')) {
            $entry->condition = $prefix.$entry->condition;
        }

        // Two Add entries resolving to the same id can only ever be the *same* extension
        // reusing an ->as() name across two unrelated ->add() calls (the prefix is always the
        // owning extension's own id, so two different extensions can never collide here no
        // matter what local name either picks) -- there is no legitimate reason for this, and
        // the registry being a plain id-keyed array means it would otherwise silently overwrite
        // the first entry outright, including its own `slot`, not just its `component`/`data`
        // -- a whole feature going quietly missing from wherever the first one rendered, with no
        // error anywhere. `Ux::replace()`/`remove()` are the real mechanism for intentionally
        // targeting an already-registered entry; a second plain `add()` never is. Same
        // fail-loudly-on-an-author-mistake convention `commands()` already uses.
        if (isset($registry[$entry->id]) && $registry[$entry->id]->action === UxAction::Add) {
            throw new \LogicException(sprintf(
                'Two Ux::add() entries both resolve to id "%s" -- give one a distinct ->as() name. '
                .'A second add() silently overwrites the first (including its own slot), it never merges with it.',
                $entry->id
            ));
        }

        $registry[$entry->id] = $entry;
    }

    /**
     * Mutates the target in place rather than replacing it in the registry, so it keeps its
     * original position -- swapping what an entry looks like is never the same thing as
     * re-ordering it. Only `component`/`data` are always overwritten; `slot`/`after`/`before`/
     * `condition` are only overwritten if this `Replace` entry actually set them (chained after
     * `Ux::replace()`) -- left `null`, the target's original value stands.
     *
     * @param  array<string, UxEntry>  $registry
     */
    protected function applyUxReplace(array &$registry, UxEntry $entry): void
    {
        $target = $registry[$entry->id] ?? null;

        if ($target === null) {
            return;
        }

        $target->component = $entry->component;
        $target->data = $entry->data;

        foreach (['slot', 'after', 'before', 'condition'] as $field) {
            if ($entry->{$field} !== null) {
                $target->{$field} = $entry->{$field};
            }
        }
    }

    /**
     * @param  array<string, UxEntry>  $registry
     */
    protected function applyUxRemove(array &$registry, UxEntry $entry): void
    {
        unset($registry[$entry->id]);
    }
}
