<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;

it('renders no fake tool buttons in the dock -- kopling-reply-dock::dock.tools is empty by default', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('kop-dock__reply')
        ->and(substr_count($html, 'class="kop-dock__tool"'))->toBe(0);
});
