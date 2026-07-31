<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;

beforeEach(fn () => Cache::forget('kopling-widgets.pulse'));

it('GET /_xhr/kopling-widgets/pulse re-renders the same widget the sidebar slot shows', function () {
    Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $html = $this->get(route('kopling-core::community/pulse.refresh'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(__('kopling-widgets::messages.pulse'))
        ->and($html)->toContain(__('kopling-widgets::messages.people'))
        ->and($html)->toContain('hx-trigger="every 60s"');
});

it('reflects a moment created after the cached count expires', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);

    $this->get(route('kopling-core::community/pulse.refresh'))->assertOk();

    Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'Hello']);
    Cache::forget('kopling-widgets.pulse');

    $html = $this->get(route('kopling-core::community/pulse.refresh'))->getContent();

    expect(substr_count($html, '>1<'))->toBeGreaterThan(0);
});
