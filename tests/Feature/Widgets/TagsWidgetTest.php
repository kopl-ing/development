<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Kopling\Discussions\Reply;
use Kopling\Tags\Tag;

beforeEach(fn () => Cache::forget('kopling-widgets.tags'));

function renderTagsWidget(): string
{
    return (string) test()->blade('<x-dynamic-component :component="$component" />', ['component' => 'kopling-widgets::tags']);
}

it('shows nothing for a tag whose moments predate the activity window', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $tag = Tag::create(['name' => 'Stale', 'slug' => 'stale']);

    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Old news', 'body' => 'Old']);
    $moment->tags()->attach($tag);
    $moment->forceFill(['created_at' => now()->subDays(30)])->save();

    expect(renderTagsWidget())->not->toContain('Stale');
});

it('shows a tag with its icon, and the author as a real (not filler) avatar', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $tag = Tag::create(['name' => 'Design', 'slug' => 'design', 'icon' => 'palette']);

    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Fresh take', 'body' => 'New']);
    $moment->tags()->attach($tag);

    $html = renderTagsWidget();

    expect($html)->toContain('Design')
        ->and($html)->toContain('<svg')
        ->and($html)->toContain('avatar-group')
        ->and($html)->toContain('title="Ada"') // the real contributor's name, not a filler dot
        ->and($html)->toContain('>A<'); // Ada's initial
});

it('caps visible avatars at 3 and shows a +N overflow count for the rest', function () {
    $tag = Tag::create(['name' => 'Design', 'slug' => 'design-overflow']);

    foreach (['Ada', 'Bob', 'Cleo', 'Dee', 'Eve'] as $name) {
        $person = Person::create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'secret']);
        $moment = Moment::create(['person_id' => $person->id, 'title' => "By {$name}", 'body' => 'New']);
        $moment->tags()->attach($tag);
    }

    $html = renderTagsWidget();

    expect(substr_count($html, 'avatar-placeholder'))->toBe(3)
        ->and($html)->toContain('+2');
});

it('keeps a tag hot from a recent reply alone, even when its moment is old', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $replier = Person::create(['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'secret']);
    $tag = Tag::create(['name' => 'Design', 'slug' => 'design']);

    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Old thread', 'body' => 'Old']);
    $moment->tags()->attach($tag);
    $moment->forceFill(['created_at' => now()->subDays(30)])->save();

    Reply::create(['moment_id' => $moment->id, 'person_id' => $replier->id, 'body' => 'Still going', 'body_html' => '<p>Still going</p>']);

    $html = renderTagsWidget();

    expect($html)->toContain('Design')
        ->and($html)->toContain('>B<'); // Bob's initial, from the recent reply
});

it('ranks by recent-activity count, not total moments ever tagged', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $quiet = Tag::create(['name' => 'Quiet', 'slug' => 'quiet']);
    $active = Tag::create(['name' => 'Active', 'slug' => 'active']);

    // "Quiet" has many old moments (high lifetime count) but nothing recent.
    foreach (range(1, 5) as $i) {
        $moment = Moment::create(['person_id' => $author->id, 'title' => "Old {$i}", 'body' => 'Old']);
        $moment->tags()->attach($quiet);
        $moment->forceFill(['created_at' => now()->subDays(60)])->save();
    }

    // "Active" has just one moment, but it's recent.
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'New', 'body' => 'New']);
    $moment->tags()->attach($active);

    $html = renderTagsWidget();

    expect($html)->toContain('Active')
        ->and($html)->not->toContain('Quiet');
});

it('survives a real file-cache serialize/unserialize round trip', function () {
    // Regression test: the `array` cache driver every other test here runs under (see
    // phpunit.xml's CACHE_STORE=array) never actually serializes anything, so a cached Carbon
    // instance round-tripped fine in every test above and still broke production, which uses
    // the real `file` driver -- a genuine PHP serialize()/unserialize() -- with "The script
    // tried to call a method on an incomplete object" the moment diffForHumans() was called on
    // what came back. Forcing the real file store here is what a value stored in this cache key
    // actually has to survive; caching a Carbon instance directly (as this file used to) fails
    // this test, caching its ->toIso8601String() instead (the actual fix) passes it.
    config(['cache.default' => 'file']);
    Cache::store('file')->forget('kopling-widgets.tags');

    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $tag = Tag::create(['name' => 'Design', 'slug' => 'design-file-cache-test']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Fresh', 'body' => 'New']);
    $moment->tags()->attach($tag);

    renderTagsWidget(); // first call: populates the file cache (real serialize())
    $html = renderTagsWidget(); // second call: reads it back (real unserialize())

    expect($html)->toContain('Design')
        ->and($html)->toContain('ago');

    Cache::store('file')->forget('kopling-widgets.tags');
});
