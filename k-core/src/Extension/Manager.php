<?php

declare(strict_types=1);

namespace Kopling\Core\Extension;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
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

/**
 * Aggregates every installed extension's declarations (permissions, ux entries, models, icons,
 * themes, portals, settings, ...) from a `RegistrationCache` when warm, otherwise by looping
 * `extensions()` and filtering by the matching contract. Local ids get prefixed with the
 * declaring extension's own `id()` so two extensions can't collide on the same name.
 */
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
     * `Core` is always first and always present, even though it isn't Composer-discovered.
     * `$includeDisabled = true` skips the `EnabledExtensions` filter (`CannotBeDisabled`
     * implementors are always kept either way) -- used by the admin extensions list.
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
     * Directory-convention paths (migrations/views/lang) an extension gets just by the directory
     * existing. Routes/css/js aren't included -- those need a target Portal, see `ExtendsPortals`.
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
     * The namespace an extension's views/translations register under -- includes the vendor so
     * two different vendors' same-named packages ("kopling/example", "acme/example") don't collide.
     */
    public function id(string $package): string
    {
        return str_replace('/', '-', $package);
    }

    /**
     * Resolves a user-typed reference -- package name, `id()` form, or short name -- back to the
     * real Composer package key. Null when nothing installed matches.
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
     * Merges a controller's own base rules/messages for `$modelClass` with whatever
     * `modelValidationRules()` aggregated for it.
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
     * Grouped by target Portal id. Targeting a Portal id that isn't registered is a silent
     * no-op, same as a dangling `ux()` `after`/`before` reference.
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
     * Keyed by a hash of its own already-validated path, not anything request-derived --
     * `ExtensionAssetController` looks a request's `key` up here rather than accepting a raw
     * path, so a request can never walk this into an arbitrary filesystem read.
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

    public static function assetUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return route('kopling-core::assets', ['key' => static::assetKey($path)]);
    }

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
     * Applies every extension's relations/hooks/casts/morph-map as a side effect (not a pure
     * aggregation) -- cached on the instance so this only ever runs once per request.
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
        $perPages = [];

        $declared->each(function (ExtendModel $model) use (&$casts, &$perPages) {
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

            if ($model->morphAlias !== null) {
                EloquentRelation::morphMap([$model->morphAlias => $class]);
            }

            $casts[$model->model] = array_merge($casts[$model->model] ?? [], $model->casts);

            if ($model->perPage !== null) {
                $perPages[$model->model] = $model->perPage;
            }
        });

        DatabaseModel::registerCasts($casts);
        DatabaseModel::registerPerPage($perPages);

        $this->models = $declared;

        return $declared;
    }

    /**
     * Validates each token against `Token`'s own catalog, throwing on an unrecognized key or a
     * value that doesn't match (a `ChangesTheme` implementor's own bug, not a dangling reference).
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
     * Every `EditorNode` any extension has voted to enable, deduped by value -- these are votes
     * into one shared catalog, not independently-namespaced declarations, so nothing is prefixed.
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
     * Resolves every extension's `Add`/`Replace`/`Remove` operations, in `extensions()` order --
     * `Replace`/`Remove` targeting an entry not yet registered (wrong order, or never existed) is
     * a no-op, so an extension can only replace/remove something an earlier one already added.
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

        if (is_string($entry->condition) && ! str_contains($entry->condition, '::')) {
            $entry->condition = $prefix.$entry->condition;
        }

        // Two extensions can never collide here (the prefix is always the owning extension's own
        // id) -- a collision means the same extension reused an ->as() name across two add()
        // calls, which would otherwise silently overwrite the first entry's slot/component/data.
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
     * Mutates the target in place to keep its original position. Only `component`/`data` are
     * always overwritten; `slot`/`after`/`before`/`condition` only if this entry actually set them.
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
