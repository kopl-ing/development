<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Tags\Tag;

/*
 * Full HTTP requests against the real routes rather than rendering the component in isolation:
 * Context::isRoute('moment') only returns true when Context's own $request actually resolved to
 * a route with a bound "moment" parameter (see Context::getRoute()) -- a bare `new Context(...)`
 * built outside a real request/response cycle has no route to check against, so it can't
 * exercise the guard this rail widget actually depends on. This is also the most valuable case
 * to prove end-to-end: it's the one place Core's chrome.blade.php Context-binding change and
 * this extension's own rail registration/guard actually meet.
 */

it('does not show related tags on the feed (no single moment to be about)', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);
    $moment->tags()->attach(Tag::create(['name' => 'Design', 'slug' => 'design-feed-test']));

    $this->get('/')->assertOk()->assertDontSee(__('kopling-tags::messages.related_tags'));
});

it('shows the moment\'s own tags, with icon/description/activity, on its discussion page', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $tag = Tag::create([
        'name' => 'Design',
        'slug' => 'design-discussion-test',
        'color' => '#E8590C',
        'icon' => 'palette',
        'description' => 'Anything about visual design.',
    ]);
    $moment->tags()->attach($tag);

    $html = $this->get(route('kopling-core::community/discussions.show', $moment))->assertOk()->getContent();

    expect($html)->toContain(__('kopling-tags::messages.related_tags'))
        ->and($html)->toContain('Design')
        ->and($html)->toContain('Anything about visual design.')
        ->and($html)->toContain('<svg')
        ->and($html)->toContain('style="color:#E8590C"')
        ->and($html)->toContain('ago');
});

it('shows nothing on a discussion page for a moment with no tags at all', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Untagged', 'body' => 'World']);

    $this->get(route('kopling-core::community/discussions.show', $moment))
        ->assertOk()
        ->assertDontSee(__('kopling-tags::messages.related_tags'));
});
