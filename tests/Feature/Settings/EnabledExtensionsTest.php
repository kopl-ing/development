<?php

declare(strict_types=1);

use Kopling\Core\Settings\EnabledExtensions;

it('treats every extension as enabled before anything has ever been toggled', function () {
    expect(EnabledExtensions::all())->toBeNull()
        ->and(EnabledExtensions::isEnabled('kopling-example'))->toBeTrue()
        ->and(EnabledExtensions::isEnabled('anything-at-all'))->toBeTrue();
});

it('bootstraps the enabled list from the supplied ids on its first disable() call', function () {
    EnabledExtensions::disable('kopling-example', ['kopling-example', 'kopling-reactions', 'kopling-tags']);

    expect(EnabledExtensions::all())->toBe(['kopling-reactions', 'kopling-tags'])
        ->and(EnabledExtensions::isEnabled('kopling-example'))->toBeFalse()
        ->and(EnabledExtensions::isEnabled('kopling-reactions'))->toBeTrue();
});

it('bootstraps the enabled list from the supplied ids on its first enable() call too', function () {
    EnabledExtensions::enable('kopling-example', ['kopling-example', 'kopling-reactions']);

    expect(EnabledExtensions::all())->toBe(['kopling-example', 'kopling-reactions']);
});

it('only adds or removes the target id on subsequent calls, without re-bootstrapping from a newer id list', function () {
    EnabledExtensions::disable('kopling-example', ['kopling-example', 'kopling-reactions', 'kopling-tags']);

    // A newly-installed extension appearing in $allIds after the list already exists doesn't
    // retroactively get added -- an explicit-list tradeoff, not a bug (an admin enables it
    // themselves once it shows up, same as any other newly-installed extension).
    EnabledExtensions::disable('kopling-tags', ['kopling-example', 'kopling-reactions', 'kopling-tags', 'kopling-new']);

    expect(EnabledExtensions::all())->toBe(['kopling-reactions']);

    EnabledExtensions::enable('kopling-example', []);

    expect(EnabledExtensions::all())->toBe(['kopling-reactions', 'kopling-example']);
});
