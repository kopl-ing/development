<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Kopling\Core\Ux\ComponentTag;

/**
 * Reflects every real `<x-k::*>` component class under `k-core/src/Ux` and asserts its Blade tag
 * (via `ComponentTag::resolve()` -- the exact same kebab-case/namespace transform
 * `Ux::add(SomeComponent::class)` itself relies on, not a reimplementation) appears literally in
 * the style guide's own Blade source. A pure string-source check, not a full render: cheap, and
 * fails the moment a new core component ships without a showcase entry -- see
 * `.docs/planning/decisions.md`'s style-guide scoping entry for the design this enforces.
 *
 * @return \Illuminate\Support\Collection<int, string>
 */
function coreUxComponentClasses(): \Illuminate\Support\Collection
{
    // `Ux::add()`-only leaves -- never invoked as a bare `<x-k::*>` tag by convention. Card's
    // Top/Body/Footer/Control resolve Author/Avatar/Content/Timestamp/Row/Column dynamically via
    // SlotResolver/<x-dynamic-component> (see Card\Top::defaults() etc.), Editor's mount resolves
    // NotionEditor the same way (Editor::defaults()), and Navigation\Item is always dispatched
    // the same way from Navigation/Sidebar/admin's own sidebar. All exercised live by the style
    // guide's Card/Editor sections (through Core's real registered defaults) -- there's just no
    // literal tag to grep for.
    $leafOnly = [
        \Kopling\Core\Ux\Card\Author::class,
        \Kopling\Core\Ux\Card\Avatar::class,
        \Kopling\Core\Ux\Card\Content::class,
        \Kopling\Core\Ux\Card\Timestamp::class,
        \Kopling\Core\Ux\Card\Row::class,
        \Kopling\Core\Ux\Card\Column::class,
        \Kopling\Core\Ux\Editor\NotionEditor::class,
        \Kopling\Core\Ux\Portal\Navigation\Item::class,
    ];

    // Card\Top/Body/Footer/Control *are* directly-tagged (`<x-k::card.top>` etc.), just written
    // literally inside core's own `views/card/card.blade.php`, not the style guide's -- rendered
    // transitively whenever `<x-k::card.card>` is used here, not re-spelled a second time.
    // Portal\Layout/Slot are the same story since the style guide's own layout
    // (`layouts/style-guide.blade.php`) stopped calling them directly and started reusing
    // `Community\Chrome` instead (see decisions.md) -- `<x-k::portal.layout>`/`<x-k::portal.slot>`
    // now live only inside `chrome.blade.php`, exercised transitively every time this extension's
    // own pages render at all, not re-spelled a second time either.
    $renderedTransitivelyByCard = [
        \Kopling\Core\Ux\Card\Top::class,
        \Kopling\Core\Ux\Card\Body::class,
        \Kopling\Core\Ux\Card\Footer::class,
        \Kopling\Core\Ux\Card\Control::class,
        \Kopling\Core\Ux\Portal\Layout::class,
        \Kopling\Core\Ux\Portal\Slot::class,
    ];

    return collect(File::allFiles(base_path('k-core/src/Ux')))
        ->filter(fn ($file) => $file->getExtension() === 'php')
        ->map(fn ($file) => 'Kopling\\Core\\Ux\\'.Str::of($file->getRelativePathname())
            ->beforeLast('.php')
            ->replace('/', '\\'))
        ->filter(fn (string $class) => class_exists($class) && is_subclass_of($class, \Illuminate\View\Component::class))
        ->reject(fn (string $class) => in_array($class, [...$leafOnly, ...$renderedTransitivelyByCard], true))
        // The rest of Community\* needs real Portal/feed wiring to mean anything -- already
        // exercised by the actual Community page, not faked here (see decisions.md's style-guide
        // scoping entry). ThemeSwitcher is the one exception: its own $data['themes']/['active']
        // override lets it render a real preview independent of that live wiring, so it's shown
        // rather than excluded.
        ->reject(fn (string $class) => str_starts_with($class, 'Kopling\\Core\\Ux\\Community\\')
            && $class !== \Kopling\Core\Ux\Community\ThemeSwitcher::class)
        ->values();
}

it('showcases every directly-invokable core <x-k::*> component', function () {
    $source = collect(File::allFiles(base_path('k-extensions/style-guide/views')))
        ->map(fn ($file) => File::get($file->getPathname()))
        ->implode("\n");

    $missing = coreUxComponentClasses()
        ->map(fn (string $class) => ComponentTag::resolve($class))
        ->reject(fn (string $tag) => str_contains($source, "<x-{$tag}"))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});

it('renders without error for a guest who cannot access it (permission gate, not a crash)', function () {
    $this->get('/style-guide')->assertForbidden();
});

it('renders successfully for a person granted access-style-guide', function () {
    $person = \Kopling\Core\People\Person::create([
        'name' => 'Ada',
        'email' => 'ada@example.test',
        'password' => 'secret',
    ]);

    $group = \Kopling\Core\People\Group::create(['name' => 'Style Guide Viewers']);
    $group->givePermissionTo('kopling-style-guide::access-style-guide');
    $person->groups()->attach($group);

    $this->actingAs($person)->get('/style-guide')->assertOk();
});
