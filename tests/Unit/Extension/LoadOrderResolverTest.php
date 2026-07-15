<?php

declare(strict_types=1);

use Kopling\Core\Core;
use Kopling\Core\Extension\LoadOrder\Directive;
use Kopling\Core\Extension\LoadOrder\Resolver;
use Tests\Fixtures\Extensions\LoadOrder\AfterOnlyExtension;
use Tests\Fixtures\Extensions\LoadOrder\BareExtension;
use Tests\Fixtures\Extensions\LoadOrder\BeforeOnlyExtension;
use Tests\Fixtures\Extensions\LoadOrder\ConfigurableExtension;
use Tests\Fixtures\Extensions\LoadOrder\SomeContract;

it('always pins kopling/core first, and sorts unrelated extensions alphabetically -- not discovery order', function () {
    $resolved = Resolver::resolve([
        'tests-fixtures/zzz-last' => new BareExtension(),
        'kopling/core' => new Core(),
        'tests-fixtures/aaa-first' => new BareExtension(),
        'tests-fixtures/mmm-mid' => new BareExtension(),
    ]);

    expect(array_keys($resolved))->toBe([
        'kopling/core',
        'tests-fixtures/aaa-first',
        'tests-fixtures/mmm-mid',
        'tests-fixtures/zzz-last',
    ]);
});

it('places a package after everything its LoadsAfter::loadAfter() names', function () {
    $resolved = Resolver::resolve([
        'tests-fixtures/b' => new BareExtension(),
        'tests-fixtures/a' => new ConfigurableExtension(after: ['tests-fixtures/b']),
    ]);

    expect(array_keys($resolved))->toBe(['tests-fixtures/b', 'tests-fixtures/a']);
});

it('places a package before everything its LoadsBefore::loadBefore() names', function () {
    $resolved = Resolver::resolve([
        'tests-fixtures/b' => new BareExtension(),
        'tests-fixtures/a' => new ConfigurableExtension(before: ['tests-fixtures/b']),
    ]);

    expect(array_keys($resolved))->toBe(['tests-fixtures/a', 'tests-fixtures/b']);
});

it('resolves an extension that implements only LoadsAfter, with no LoadsBefore at all', function () {
    $resolved = Resolver::resolve([
        'tests-fixtures/b' => new BareExtension(),
        'tests-fixtures/a' => new AfterOnlyExtension(after: ['tests-fixtures/b']),
    ]);

    expect(array_keys($resolved))->toBe(['tests-fixtures/b', 'tests-fixtures/a']);
});

it('resolves an extension that implements only LoadsBefore, with no LoadsAfter at all', function () {
    $resolved = Resolver::resolve([
        'tests-fixtures/b' => new BareExtension(),
        'tests-fixtures/a' => new BeforeOnlyExtension(before: ['tests-fixtures/b']),
    ]);

    expect(array_keys($resolved))->toBe(['tests-fixtures/a', 'tests-fixtures/b']);
});

it('silently ignores a LoadsAfter reference to a package that was never discovered', function () {
    $resolved = Resolver::resolve([
        'tests-fixtures/lonely' => new ConfigurableExtension(after: ['tests-fixtures/never-installed']),
    ]);

    expect(array_keys($resolved))->toBe(['tests-fixtures/lonely']);
});

it('lets an InfluencesLoadOrder rule place every implementor of a contract after the declaring package, without naming it', function () {
    $resolved = Resolver::resolve([
        'tests-fixtures/follower' => new ConfigurableExtension(),
        'tests-fixtures/owner' => new ConfigurableExtension(rules: [SomeContract::class => Directive::After]),
    ]);

    expect(array_search('tests-fixtures/owner', array_keys($resolved), true))
        ->toBeLessThan(array_search('tests-fixtures/follower', array_keys($resolved), true));
});

it('lets an extension\'s own explicit LoadsBefore opt out of another\'s inferred InfluencesLoadOrder rule', function () {
    $resolved = Resolver::resolve([
        'tests-fixtures/follower' => new ConfigurableExtension(),
        'tests-fixtures/rebel' => new ConfigurableExtension(before: ['tests-fixtures/owner']),
        'tests-fixtures/owner' => new ConfigurableExtension(rules: [SomeContract::class => Directive::After]),
    ]);

    $order = array_keys($resolved);

    // follower has no opinion of its own, so the inferred rule applies: it loads after owner.
    expect(array_search('tests-fixtures/owner', $order, true))
        ->toBeLessThan(array_search('tests-fixtures/follower', $order, true))
        // rebel's own explicit loadBefore() wins over owner's inferred "after me" rule.
        ->and(array_search('tests-fixtures/rebel', $order, true))
        ->toBeLessThan(array_search('tests-fixtures/owner', $order, true));
});

it('throws naming the packages involved when load order constraints form a genuine cycle', function () {
    $extensions = [
        'tests-fixtures/cycle-a' => new ConfigurableExtension(after: ['tests-fixtures/cycle-b']),
        'tests-fixtures/cycle-b' => new ConfigurableExtension(after: ['tests-fixtures/cycle-a']),
    ];

    try {
        Resolver::resolve($extensions);
        $this->fail('Expected a LogicException to be thrown.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())
            ->toContain('tests-fixtures/cycle-a')
            ->toContain('tests-fixtures/cycle-b');
    }
});
