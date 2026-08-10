<?php

declare(strict_types=1);

use Kopling\Core\Content\Moment;
use Kopling\Core\People\Person;
use Symfony\Component\DomCrawler\Crawler;

it('renders no fake tool buttons in the dock -- kopling-reply-dock::dock.tools is empty by default', function () {
    $author = Person::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => 'secret']);
    $moment = Moment::create(['person_id' => $author->id, 'title' => 'Hello', 'body' => 'World']);

    $html = $this->actingAs($author)
        ->get(route('kopling-core::community/discussions.show', $moment->id))
        ->assertOk()
        ->getContent();

    $crawler = new Crawler($html);

    expect($crawler->filter('.kop-dock__reply'))->toHaveCount(1)
        ->and($crawler->filter('.kop-dock__tool'))->toHaveCount(0);
});
