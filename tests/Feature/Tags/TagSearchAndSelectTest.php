<?php

declare(strict_types=1);

use Kopling\Core\People\Person;
use Kopling\Tags\Tag;

function personForTagSearch(): Person
{
    return Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
}

it('GET /_tags/search includes color and a rendered icon svg per result', function () {
    $person = personForTagSearch();

    Tag::create(['name' => 'Design', 'slug' => 'design-search-test', 'color' => '#E8590C', 'icon' => 'palette']);

    $json = $this->actingAs($person)
        ->get('/_tags/search?q=Design')
        ->assertOk()
        ->json();

    expect($json)->toHaveCount(1)
        ->and($json[0]['label'])->toBe('Design')
        ->and($json[0]['color'])->toBe('#E8590C')
        ->and($json[0]['icon'])->toContain('<svg')
        ->and($json[0]['icon'])->toContain('style="color:#E8590C"');
});

it('GET /_tags/search returns a null icon for a tag with no icon set', function () {
    $person = personForTagSearch();

    Tag::create(['name' => 'Plain', 'slug' => 'plain-search-test']);

    $json = $this->actingAs($person)->get('/_tags/search?q=Plain')->assertOk()->json();

    expect($json[0]['icon'])->toBeNull()
        ->and($json[0]['color'])->toBeNull();
});

it('the tag select component carries color/icon through for already-selected tags', function () {
    $tag = Tag::create(['name' => 'Design', 'slug' => 'design-select-test', 'color' => '#E8590C', 'icon' => 'palette']);

    $html = (string) $this->blade('<x-dynamic-component :component="$c" :data="$data" />', [
        'c' => 'kopling-tags::select',
        'data' => ['value' => [$tag->id]],
    ]);

    expect($html)->toContain('#E8590C')
        ->and($html)->toContain('&lt;svg'); // JSON-encoded SVG inside the HTML-escaped data-initial-value attribute
});
