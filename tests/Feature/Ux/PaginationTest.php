<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Kopling\Core\Ux\Context;
use Tests\Fixtures\Extensions\ModelExtender\Gadget;

/*
 * Reuses the `fixture_gadgets` table/model `ContextTest.php` already establishes for a real,
 * paginatable `Builder` subject -- `Pagination` renders through `Context::getSubjectPaginator()`,
 * so it needs a real query behind it, not a bare array/DTO.
 */

beforeEach(function () {
    Schema::create('fixture_gadgets', function ($table) {
        $table->id();
        $table->text('metadata')->nullable();
    });
});

it('renders nothing when there is only one page', function () {
    Gadget::create();
    Gadget::create();

    $html = (string) $this->blade(
        '<x-k::page.pagination :context="$context" />',
        ['context' => new Context(subject: Gadget::query())]
    );

    expect(trim($html))->toBe('');
});

it('renders page links, marking the current page active and disabling previous on page one', function () {
    // One more than a single page holds, whatever Gadget::$perPage is currently set to -- not a
    // hardcoded count, so this doesn't silently start passing/failing every time that changes.
    collect(range(1, (new Gadget())->getPerPage() + 1))->each(fn () => Gadget::create());

    $html = (string) $this->blade(
        '<x-k::page.pagination :context="$context" />',
        ['context' => new Context(subject: Gadget::query())]
    );

    expect($html)->toContain('aria-current="page"')
        ->and($html)->toContain('>1<')
        ->and($html)->toContain('>2<')
        ->and($html)->toContain('aria-label="Previous"')
        ->and($html)->toContain('aria-label="Next"')
        ->and($html)->toContain('page=2');
});

it('links back to the previous page and stops offering a next page on the last page', function () {
    // One more than a single page holds, whatever Gadget::$perPage is currently set to -- not a
    // hardcoded count, so this doesn't silently start passing/failing every time that changes.
    collect(range(1, (new Gadget())->getPerPage() + 1))->each(fn () => Gadget::create());

    request()->merge(['page' => 2]);

    $html = (string) $this->blade(
        '<x-k::page.pagination :context="$context" />',
        ['context' => new Context(subject: Gadget::query())]
    );

    expect($html)->toContain('page=1')
        ->and($html)->not->toContain('page=3');
});

it('stays a plain, un-boosted link when no target is given', function () {
    collect(range(1, (new Gadget())->getPerPage() + 1))->each(fn () => Gadget::create());

    $html = (string) $this->blade(
        '<x-k::page.pagination :context="$context" />',
        ['context' => new Context(subject: Gadget::query())]
    );

    expect($html)->not->toContain('hx-boost');
});

it('boosts its own links against the given target, without touching hx-boost inheritance elsewhere', function () {
    collect(range(1, (new Gadget())->getPerPage() + 1))->each(fn () => Gadget::create());

    $html = (string) $this->blade(
        '<x-k::page.pagination :context="$context" target="#gadgets-wrapper" />',
        ['context' => new Context(subject: Gadget::query())]
    );

    expect($html)->toContain('hx-boost:inherited="true"')
        ->and($html)->toContain('hx-target:inherited="#gadgets-wrapper"')
        ->and($html)->toContain('hx-select:inherited="#gadgets-wrapper"')
        ->and($html)->toContain('hx-push-url:inherited="true"')
        // Scoped to this component's own <nav>, not left as a bare (implicitly-inheriting in
        // htmx 4 only via the wrapping element's own attribute, never plain `hx-boost`) toggle
        // that a caller's wider wrapper could pick up and apply to unrelated links.
        ->and(substr_count($html, 'hx-boost'))->toBe(1);
});
