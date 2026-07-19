<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Tags\Tag;

/*
 * The badge row's own wrapping div class combo, not "badge-sm" alone -- the tag show page also
 * renders Community's left sidebar (widgets' own "popular tags" card), which uses that same
 * daisyUI class on its own, unrelated badges. This one string is unique to this component.
 */
function cardTagRowMarker(): string
{
    return 'mb-1 flex flex-wrap items-center gap-1.5';
}

function momentWithAuthorForBadge(): Moment
{
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    return Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
}

it('shows a tag badge with its icon on the feed', function () {
    $tag = Tag::create(['name' => 'Design', 'slug' => 'design-badge-feed', 'icon' => 'palette', 'color' => '#E8590C']);
    $moment = momentWithAuthorForBadge();
    $moment->tags()->attach($tag);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('Design')
        ->and($html)->toContain('<svg')
        ->and(substr_count($html, cardTagRowMarker()))->toBe(1);
});

it('suppresses the badge row entirely when the moment carries only the current tag page\'s own tag', function () {
    $tag = Tag::create(['name' => 'Design', 'slug' => 'design-badge-suppress']);
    $moment = momentWithAuthorForBadge();
    $moment->tags()->attach($tag);

    $html = $this->get('/tag/design-badge-suppress')->assertOk()->getContent();

    expect(substr_count($html, cardTagRowMarker()))->toBe(0);
});

it('still shows the badge row, with every tag, when a moment has more than one', function () {
    $design = Tag::create(['name' => 'Design', 'slug' => 'design-badge-multi']);
    $ux = Tag::create(['name' => 'UX', 'slug' => 'ux-badge-multi']);
    $moment = momentWithAuthorForBadge();
    $moment->tags()->attach([$design->id, $ux->id]);

    $html = $this->get('/tag/design-badge-multi')->assertOk()->getContent();

    expect(substr_count($html, cardTagRowMarker()))->toBe(1)
        ->and($html)->toContain('Design')
        ->and($html)->toContain('UX');
});

it('does not suppress the badge row on any other page than the tag\'s own', function () {
    $tag = Tag::create(['name' => 'Design', 'slug' => 'design-badge-elsewhere']);
    $moment = momentWithAuthorForBadge();
    $moment->tags()->attach($tag);

    // The feed, not /tag/{slug} -- the same single-tag moment must still show its one badge here.
    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, cardTagRowMarker()))->toBe(1);
});
